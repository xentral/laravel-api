<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\Query\QueryBuilder;

it('uses our custom query builder request', function () {
    $qb = new QueryBuilder(
        mock(Builder::class),
        Request::capture(),
    );
    $ref = new ReflectionProperty($qb, 'request');

    expect($ref->getValue($qb))->toBeInstanceOf(QueryBuilderRequest::class);
});
