<?php declare(strict_types=1);

use Spatie\QueryBuilder\AllowedFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\Filters\StringOperatorFilter;

describe('QueryFilter', function () {
    it('can create an AllowedFilter using make method with default operators', function () {
        $filter = new QueryFilter('test');
        $result = $filter->make('test_field');

        expect($result)->toBeInstanceOf(AllowedFilter::class)
            ->and($result->getName())->toBe('test_field')
            ->and($result->getInternalName())->toBe('test_field');
    });

    it('uses all FilterOperator cases as default when no allowedOperators provided', function () {
        $filter = new QueryFilter('test');
        $result = $filter->make('test_field');

        $customFilter = $result->getFilterClass();
        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->allowedOperators())->toEqual(FilterOperator::cases());
    });

    it('can create an AllowedFilter with custom allowed operators', function () {
        $filter = new QueryFilter('test');
        $customOperators = [FilterOperator::EQUALS, FilterOperator::NOT_EQUALS];
        $result = $filter->make('test_field', $customOperators);

        $customFilter = $result->getFilterClass();
        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->allowedOperators())->toEqual($customOperators);
    });

    it('can create an AllowedFilter with custom internal name', function () {
        $filter = new QueryFilter('test');
        $result = $filter->make('external_name', [], 'internal_name');

        expect($result->getName())->toBe('external_name')
            ->and($result->getInternalName())->toBe('internal_name');
    });

    it('can create an AllowedFilter with enum parameter', function () {
        $filter = new QueryFilter('test');
        $result = $filter->make('test_field', [], null, 'SomeEnum');

        $customFilter = $result->getFilterClass();
        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->enum())->toBe('SomeEnum');
    });

    it('can create an AllowedFilter with all parameters', function () {
        $filter = new QueryFilter('test');
        $customOperators = [FilterOperator::CONTAINS, FilterOperator::STARTS_WITH];
        $result = $filter->make('search_field', $customOperators, 'db_search_column', 'SearchEnum');

        expect($result)->toBeInstanceOf(AllowedFilter::class)
            ->and($result->getName())->toBe('search_field')
            ->and($result->getInternalName())->toBe('db_search_column');

        $customFilter = $result->getFilterClass();
        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->allowedOperators())->toEqual($customOperators)
            ->and($customFilter->enum())->toBe('SearchEnum');
    });

    it('can create an AllowedFilter with empty allowed operators array', function () {
        $filter = new QueryFilter('test');
        $result = $filter->make('test_field', []);

        $customFilter = $result->getFilterClass();
        // When empty array is passed, it should default to all FilterOperator cases
        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->allowedOperators())->toEqual(FilterOperator::cases());
    });
});
