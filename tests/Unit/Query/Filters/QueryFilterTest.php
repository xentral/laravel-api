<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Workbench\App\Enums\TestStatusEnum;
use Workbench\App\Models\Invoice;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\IdCallbackFilter;
use Xentral\LaravelApi\Query\Filters\IdFilterTarget;
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

    it('can create an id filter from a callback', function () {
        $result = QueryFilter::identifierCallback(
            'categories.id',
            fn (Builder $query, array $ids) => $query->whereIn('typ', $ids),
            IdFilterTarget::Relation,
        );

        expect($result)->toBeInstanceOf(AllowedFilter::class)
            ->and($result->getName())->toBe('categories.id')
            ->and($result->getFilterClass())->toBeInstanceOf(IdCallbackFilter::class);
    });

    it('accepts any callable, not only a closure, as the id predicate', function () {
        $predicate = new class
        {
            public function __invoke(Builder $query, array $ids): void
            {
                $query->whereIn('typ', $ids);
            }
        };

        $result = QueryFilter::identifierCallback('merchandiseGroup.id', $predicate, IdFilterTarget::Column);

        expect($result->getFilterClass())->toBeInstanceOf(IdCallbackFilter::class);
    });
});

describe('QueryFilter::enum', function () {
    it('carries exactly the four enum operators', function () {
        $customFilter = QueryFilter::enum('status', TestStatusEnum::class)->getFilterClass();

        expect($customFilter)->toBeInstanceOf(StringOperatorFilter::class)
            ->and($customFilter->allowedOperators())->toEqualCanonicalizing([
                FilterOperator::EQUALS,
                FilterOperator::NOT_EQUALS,
                FilterOperator::IN,
                FilterOperator::NOT_IN,
            ]);
    });

    it('rejects a string-only operator on an enum-backed key', function () {
        $customFilter = QueryFilter::enum('status', TestStatusEnum::class)->getFilterClass();

        $customFilter(Invoice::query(), ['operator' => 'contains', 'value' => 'act'], 'status');
    })->throws(ValidationException::class);

    it('rejects isNull on an enum-backed key instead of matching the zero case', function () {
        $customFilter = QueryFilter::enum('status', TestStatusEnum::class)->getFilterClass();

        $customFilter(Invoice::query(), ['operator' => 'isNull'], 'status');
    })->throws(ValidationException::class);

    it('resolves enum values through the legacy MAPPING like the string helper does', function () {
        Invoice::factory()->create(['status' => 'old_value1']);
        Invoice::factory()->create(['status' => 'old_value2']);

        $query = Invoice::query();
        $customFilter = QueryFilter::enum('status', TestStatusEnum::class)->getFilterClass();
        $customFilter($query, ['operator' => 'equals', 'value' => 'active'], 'status');

        expect($query->count())->toBe(1);
    });

    it('respects the internal name mapping', function () {
        $result = QueryFilter::enum('status', TestStatusEnum::class, 'internal_status');

        expect($result->getName())->toBe('status')
            ->and($result->getInternalName())->toBe('internal_status');
    });

    it('accepts a widened operator set for a nullable enum column', function () {
        $customFilter = QueryFilter::enum('status', TestStatusEnum::class, operators: [
            ...FilterOperator::ENUM,
            FilterOperator::IS_NULL,
            FilterOperator::IS_NOT_NULL,
        ])->getFilterClass();

        expect($customFilter->allowedOperators())->toEqualCanonicalizing([
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::IN,
            FilterOperator::NOT_IN,
            FilterOperator::IS_NULL,
            FilterOperator::IS_NOT_NULL,
        ]);

        $customFilter(Invoice::query(), ['operator' => 'isNull'], 'status');
    });
});
