<?php declare(strict_types=1);

use League\OpenAPIValidation\PSR7\Exception\NoPath;
use Workbench\App\Models\TestModel;
use Xentral\LaravelTesting\OpenApi\ValidatesOpenApiSpec;

uses(ValidatesOpenApiSpec::class);

beforeEach(function () {
    TestModel::factory()->count(5)->create();
    $this->schemaFilePath(dirname(__DIR__, 2).'/workbench/openapi.yml');
});

it('validates requests against OpenAPI spec automatically: success', function () {
    $this->getJson('/api/v1/test-models')->assertOk();
});

it('validates requests against OpenAPI spec automatically:fail', function () {
    $this->expectException(NoPath::class);
    $this->getJson('/api/v1/wrong-models/1');
});
