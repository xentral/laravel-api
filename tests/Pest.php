<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Query\QueryBuilder;
use Xentral\LaravelApi\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

function createQueryFromFilterRequest(array $filters, ?string $model = null): QueryBuilder
{
    $model ??= TestModel::class;

    $request = new Request([
        'filter' => $filters,
    ]);

    return QueryBuilder::for($model, $request);
}

function fixture(string $name): string
{
    return __DIR__.'/Fixtures/'.ltrim($name, '/');
}
