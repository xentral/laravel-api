<?php declare(strict_types=1);

use Xentral\LaravelApi\OpenApi\Filters\DateFilter;
use Xentral\LaravelApi\Query\Filters\DateOperatorFilter;
use Xentral\LaravelApi\Query\Filters\FilterOperator;

it('documents the same operators as the runtime date filter by default', function () {
    $filter = new DateFilter(name: 'issuedAt');

    expect($filter->operators)->toBe((new DateOperatorFilter)->allowedOperators());
});

it('documents isNull and isNotNull by default', function () {
    $filter = new DateFilter(name: 'issuedAt');

    expect($filter->operators)->toContain(FilterOperator::IS_NULL)
        ->and($filter->operators)->toContain(FilterOperator::IS_NOT_NULL);
});

it('allows custom operators', function () {
    $filter = new DateFilter(name: 'issuedAt', operators: [FilterOperator::EQUALS]);

    expect($filter->operators)->toBe([FilterOperator::EQUALS]);
});
