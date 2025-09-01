<?php declare(strict_types=1);

use Spatie\QueryBuilder\Exceptions\InvalidFilterQuery;
use Workbench\App\Enums\TestStatusEnum;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

beforeEach(function () {
    $this->models = TestModel::factory()->count(3)->create();
});

describe('QueryFilter Endpoint Validation', function () {
    it('throws InvalidFilterQuery for filters not in allowedFilters list', function () {
        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'equals',
                'value' => 'test',
            ],
        ])
            ->allowedFilters('id') // Only 'id' is allowed, but 'name' is being used
            ->get())
            ->toThrow(function (InvalidFilterQuery $e) {
                expect($e->unknownFilters->toArray())->toContain('name');
                expect($e->allowedFilters->toArray())->toContain('id');
            });
    });

    it('allows valid filters in allowedFilters list', function () {
        $result = createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'equals',
                'value' => $this->models->first()->name,
            ],
        ])
            ->allowedFilters('name') // 'name' is allowed
            ->get();

        expect($result)->toHaveCount(1);
    });

    it('throws InvalidFilterQuery for multiple filters when one is not allowed', function () {
        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'equals',
                'value' => 'test',
            ],
            [
                'key' => 'email', // This filter is not allowed
                'op' => 'contains',
                'value' => 'test@example.com',
            ],
        ])
            ->allowedFilters('name') // Only 'name' is allowed
            ->get())
            ->toThrow(InvalidFilterQuery::class);
    });

    it('allows multiple valid filters', function () {
        $result = createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'equals',
                'value' => $this->models->first()->name,
            ],
            [
                'key' => 'id',
                'op' => 'equals',
                'value' => $this->models->first()->id,
            ],
        ])
            ->allowedFilters('name', 'id') // Both filters are allowed
            ->get();

        expect($result)->toHaveCount(1);
    });

    it('works with custom QueryFilter make method filters', function () {
        $filter = new QueryFilter('test');
        $nameFilter = $filter->make('name', [FilterOperator::EQUALS, FilterOperator::CONTAINS]);
        $idFilter = $filter->make('id', [FilterOperator::EQUALS]);

        $result = createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'equals',
                'value' => $this->models->first()->name,
            ],
        ])
            ->allowedFilters($nameFilter, $idFilter)
            ->get();

        expect($result)->toHaveCount(1);
    });

    it('validates custom filter operators at endpoint level', function () {
        $filter = new QueryFilter('test');
        $nameFilter = $filter->make('name', [FilterOperator::EQUALS]); // Only EQUALS allowed

        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'contains', // CONTAINS not allowed
                'value' => 'test',
            ],
        ])
            ->allowedFilters($nameFilter)
            ->get())
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('validates enum filters at endpoint level', function () {
        $filter = new QueryFilter('test');
        $statusFilter = $filter->make('status', [FilterOperator::EQUALS], null, TestStatusEnum::class);

        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'status',
                'op' => 'equals',
                'value' => 'invalid_status', // Invalid enum value
            ],
        ])
            ->allowedFilters($statusFilter)
            ->get())
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('allows valid enum values at endpoint level', function () {
        $filter = new QueryFilter('test');
        $statusFilter = $filter->make('status', [FilterOperator::EQUALS], null, TestStatusEnum::class);

        // This should not throw an exception since 'active' is a valid enum value
        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'status',
                'op' => 'equals',
                'value' => 'active',
            ],
        ])
            ->allowedFilters($statusFilter)
            ->get())
            ->not->toThrow(\Illuminate\Validation\ValidationException::class);
    });

    it('provides helpful error messages for invalid filters', function () {
        try {
            createQueryFromFilterRequest([
                [
                    'key' => 'unknown_field',
                    'op' => 'equals',
                    'value' => 'test',
                ],
            ])
                ->allowedFilters('name', 'id')
                ->get();

            expect(false)->toBeTrue('Exception should have been thrown');
        } catch (InvalidFilterQuery $e) {
            expect($e->unknownFilters->toArray())->toContain('unknown_field');
            expect($e->allowedFilters->toArray())->toContain('name');
            expect($e->allowedFilters->toArray())->toContain('id');
        }
    });

    it('handles complex filter scenarios with multiple custom filters', function () {
        $filter = new QueryFilter('test');
        $nameFilter = $filter->make('name', [FilterOperator::CONTAINS, FilterOperator::STARTS_WITH]);
        $statusFilter = $filter->make('status', [FilterOperator::IN], null, TestStatusEnum::class);
        $idFilter = $filter->make('id', [FilterOperator::EQUALS, FilterOperator::IN]);

        // This should work - all filters are allowed and have valid operators/values
        expect(fn () => createQueryFromFilterRequest([
            [
                'key' => 'name',
                'op' => 'contains',
                'value' => 'test',
            ],
            [
                'key' => 'status',
                'op' => 'in',
                'value' => ['active', 'pending'],
            ],
            [
                'key' => 'id',
                'op' => 'in',
                'value' => [1, 2, 3],
            ],
        ])
            ->allowedFilters($nameFilter, $statusFilter, $idFilter)
            ->get())
            ->not->toThrow(\Exception::class);
    });
});
