<?php declare(strict_types=1);

use Xentral\LaravelApi\Query\Filters\FilterOperator;

describe('FilterOperator::positiveForm', function () {
    it('maps each negative operator to its positive counterpart', function () {
        expect(FilterOperator::NOT_EQUALS->positiveForm())->toBe(FilterOperator::EQUALS)
            ->and(FilterOperator::NOT_IN->positiveForm())->toBe(FilterOperator::IN)
            ->and(FilterOperator::NOT_CONTAINS->positiveForm())->toBe(FilterOperator::CONTAINS);
    });

    it('returns null for operators that have no positive counterpart', function () {
        expect(FilterOperator::EQUALS->positiveForm())->toBeNull()
            ->and(FilterOperator::IN->positiveForm())->toBeNull()
            ->and(FilterOperator::CONTAINS->positiveForm())->toBeNull()
            ->and(FilterOperator::STARTS_WITH->positiveForm())->toBeNull()
            ->and(FilterOperator::GREATER_THAN->positiveForm())->toBeNull()
            ->and(FilterOperator::IS_NULL->positiveForm())->toBeNull()
            ->and(FilterOperator::IS_NOT_NULL->positiveForm())->toBeNull();
    });
});
