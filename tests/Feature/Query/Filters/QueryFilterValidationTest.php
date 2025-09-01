<?php declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Workbench\App\Enums\TestStatusEnum;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

beforeEach(function () {
    $this->models = TestModel::factory()->count(3)->create();
});

describe('QueryFilter Validation', function () {
    it('throws validation exception for unsupported operator', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::EQUALS]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'contains',
            'value' => 'test',
        ], 'name'))
            ->toThrow(ValidationException::class, 'Unsupported filter operator: contains');
    });

    it('throws validation exception for invalid operator string', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::EQUALS]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'invalidOperator',
            'value' => 'test',
        ], 'name'))
            ->toThrow(ValidationException::class);
    });

    it('throws validation exception for array value with non-array operator', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::EQUALS, FilterOperator::CONTAINS]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => ['test1', 'test2'],
        ], 'name'))
            ->toThrow(ValidationException::class, 'Unsupported filter operator: equals. Only in and notIn are allowed for multiple values');
    });

    it('throws validation exception for array value with contains operator', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::CONTAINS]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'contains',
            'value' => ['test1', 'test2'],
        ], 'name'))
            ->toThrow(ValidationException::class, 'Unsupported filter operator: contains. Only in and notIn are allowed for multiple values');
    });

    it('allows array values with IN operator', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::IN]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'in',
            'value' => ['test1', 'test2'],
        ], 'name'))
            ->not->toThrow(ValidationException::class);
    });

    it('allows array values with NOT_IN operator', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::NOT_IN]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'notIn',
            'value' => ['test1', 'test2'],
        ], 'name'))
            ->not->toThrow(ValidationException::class);
    });

    it('handles empty value gracefully', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', [FilterOperator::EQUALS]);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [], 'name'))
            ->not->toThrow(ValidationException::class);
    });

    it('validates all operators are allowed when empty array is passed', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('name', []); // Should allow all operators

        $query = TestModel::query();

        // Test that any valid operator works
        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => 'test',
        ], 'name'))
            ->not->toThrow(ValidationException::class);

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'contains',
            'value' => 'test',
        ], 'name'))
            ->not->toThrow(ValidationException::class);

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'in',
            'value' => ['test1', 'test2'],
        ], 'name'))
            ->not->toThrow(ValidationException::class);
    });

    it('throws validation exception for invalid enum value', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('status', [FilterOperator::EQUALS], null, TestStatusEnum::class);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => 'invalid_status',
        ], 'status'))
            ->toThrow(ValidationException::class, 'Invalid value: invalid_status. Valid values are: active, inactive, pending');
    });

    it('allows valid enum values', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('status', [FilterOperator::EQUALS], null, TestStatusEnum::class);

        $query = TestModel::query();

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => 'active',
        ], 'status'))
            ->not->toThrow(ValidationException::class);

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => 'inactive',
        ], 'status'))
            ->not->toThrow(ValidationException::class);

        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'equals',
            'value' => 'pending',
        ], 'status'))
            ->not->toThrow(ValidationException::class);
    });

    it('validates enum values in arrays', function () {
        $filter = new QueryFilter('test');
        $allowedFilter = $filter->make('status', [FilterOperator::IN], null, TestStatusEnum::class);

        $query = TestModel::query();

        // Valid enum values in array
        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'in',
            'value' => ['active', 'pending'],
        ], 'status'))
            ->not->toThrow(ValidationException::class);

        // Invalid enum value in array
        expect(fn () => $allowedFilter->getFilterClass()($query, [
            'operator' => 'in',
            'value' => ['active', 'invalid_status'],
        ], 'status'))
            ->toThrow(ValidationException::class, 'Invalid value: invalid_status. Valid values are: active, inactive, pending');
    });
});
