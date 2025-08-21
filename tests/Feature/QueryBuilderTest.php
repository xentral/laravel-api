<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\QueryBuilder;

it('uses our custom query builder request', function () {
    $qb = new QueryBuilder(
        mock(Builder::class),
        Request::capture(),
    );
    $ref = new ReflectionProperty($qb, 'request');

    expect($ref->getValue($qb))->toBeInstanceOf(QueryBuilderRequest::class);
});

it('uses snake_case per_page parameter for pagination', function () {
    TestModel::factory()->count(50)->create();

    $request = Request::create('/', 'GET', ['per_page' => 25]);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(25);
});

it('uses camelCase perPage parameter for pagination', function () {
    TestModel::factory()->count(50)->create();

    $request = Request::create('/', 'GET', ['perPage' => 30]);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(30);
});

it('prioritizes per_page over perPage when both are provided', function () {
    TestModel::factory()->count(50)->create();

    $request = Request::create('/', 'GET', ['per_page' => 20, 'perPage' => 35]);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(20);
});

it('uses default pagination size when neither parameter is provided', function () {
    TestModel::factory()->count(50)->create();

    $request = Request::create('/', 'GET', []);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(15);
});

it('caps pagination at maximum of 100 items per page', function () {
    TestModel::factory()->count(150)->create();

    $request = Request::create('/', 'GET', ['per_page' => 150]);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(100);
});

it('caps pagination at maximum of 100 items per page with camelCase', function () {
    TestModel::factory()->count(150)->create();

    $request = Request::create('/', 'GET', ['perPage' => 150]);
    $qb = new QueryBuilder(TestModel::query(), $request);

    $result = $qb->apiPaginate(allowedTypes: PaginationType::SIMPLE);

    expect($result->perPage())->toBe(100);
});
