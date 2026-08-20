<?php declare(strict_types=1);

use Workbench\App\Enums\TestStatusEnum;
use Xentral\LaravelApi\OpenApi\Filters\BooleanFilter;
use Xentral\LaravelApi\OpenApi\Filters\DateFilter;
use Xentral\LaravelApi\OpenApi\Filters\DateTimeFilter;
use Xentral\LaravelApi\OpenApi\Filters\EnumFilter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\NumberFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;
use Xentral\LaravelApi\Query\Filters\IdFilterTarget;
use Xentral\LaravelApi\Query\Filters\QueryFilter;

/**
 * Pins the operator contract per filter family: the operators the spec
 * attribute documents by default are exactly the operators the matching
 * runtime helper accepts. A key that answers requests for an operator its
 * documentation omits (or vice versa) is a drift regression.
 */
describe('spec attribute defaults match the runtime operator sets', function () {
    it('aligns the identifier family', function () {
        expect((new IdFilter)->operators)
            ->toEqualCanonicalizing(QueryFilter::identifier()->getFilterClass()->allowedOperators());
    });

    it('aligns the string family', function () {
        expect((new StringFilter('name'))->operators)
            ->toEqualCanonicalizing(QueryFilter::string('name')->getFilterClass()->allowedOperators());
    });

    it('aligns the number family', function () {
        expect((new NumberFilter('amount'))->operators)
            ->toEqualCanonicalizing(QueryFilter::number('amount')->getFilterClass()->allowedOperators());
    });

    it('aligns the date family', function () {
        expect((new DateFilter('createdAt'))->operators)
            ->toEqualCanonicalizing(QueryFilter::date('createdAt')->getFilterClass()->allowedOperators());
    });

    it('aligns the datetime family', function () {
        expect((new DateTimeFilter('updatedAt'))->operators)
            ->toEqualCanonicalizing(QueryFilter::datetime('updatedAt')->getFilterClass()->allowedOperators());
    });

    it('aligns the boolean family', function () {
        expect((new BooleanFilter('isActive'))->operators)
            ->toEqualCanonicalizing(QueryFilter::boolean('isActive')->getFilterClass()->allowedOperators());
    });

    it('aligns the enum family', function () {
        expect((new EnumFilter('status', TestStatusEnum::class))->operators)
            ->toEqualCanonicalizing(QueryFilter::enum('status', TestStatusEnum::class)->getFilterClass()->allowedOperators());
    });

    it('aligns an id callback key with an IdFilter restricted to the membership set', function () {
        $runtime = QueryFilter::identifierCallback(
            'categories.id',
            fn ($query, array $ids) => $query->whereIn('typ', $ids),
            IdFilterTarget::Relation,
        )->getFilterClass();

        expect((new IdFilter('categories.id', operators: FilterOperator::MEMBERSHIP))->operators)
            ->toEqualCanonicalizing($runtime->allowedOperators());
    });
});
