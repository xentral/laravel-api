<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Workbench\App\Http\Filter\FilterCollection;
use Workbench\App\Http\Requests\CreateTestModelRequest;
use Workbench\App\Http\Requests\UpdateTestModelRequest;
use Workbench\App\Http\Resources\TestResource;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\Attributes\ActionEndpoint;
use Xentral\LaravelApi\Attributes\DeleteEndpoint;
use Xentral\LaravelApi\Attributes\FilterParameter;
use Xentral\LaravelApi\Attributes\GetEndpoint;
use Xentral\LaravelApi\Attributes\IdFilter;
use Xentral\LaravelApi\Attributes\ListEndpoint;
use Xentral\LaravelApi\Attributes\PatchEndpoint;
use Xentral\LaravelApi\Attributes\PostEndpoint;
use Xentral\LaravelApi\Attributes\StringFilter;
use Xentral\LaravelApi\Http\Filters\QueryFilter;
use Xentral\LaravelApi\QueryBuilder;

class TestController
{
    #[ListEndpoint(
        path: '/api/v1/test-models',
        resource: TestResource::class,
        description: 'List test resources',
        includes: ['test model'],
        parameters: [
            new FilterParameter([
                new IdFilter,
                new StringFilter(name: 'name'),
                new StringFilter(name: 'status'),
                new FilterCollection,
            ]),
        ],
        maxPageSize: 1337,
        featureFlag: 'beta-users',
        scopes: 'test-models:read',
    )]
    public function index(): AnonymousResourceCollection
    {
        return TestResource::collection(
            QueryBuilder::for(TestModel::class)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('name'),
                    QueryFilter::string('status'),
                    new FilterCollection,
                )
                ->get()
        );
    }

    #[GetEndpoint(
        path: '/api/v1/test-models/{id}',
        resource: TestResource::class,
        description: 'get test resource',
        includes: ['test resource'],
    )]
    public function show(int $id): TestResource
    {
        return new TestResource(TestModel::query()->findOrFail($id));
    }

    #[PostEndpoint(
        path: '/api/v1/test-models',
        request: CreateTestModelRequest::class,
        resource: TestResource::class,
        description: 'update test resource',
    )]
    public function create(CreateTestModelRequest $request): TestResource
    {
        $testModel = TestModel::query()->create($request->validated());

        return new TestResource($testModel);
    }

    #[PatchEndpoint(
        path: '/api/v1/test-models/{id}',
        request: UpdateTestModelRequest::class,
        resource: TestResource::class,
        description: 'update test resource',
    )]
    public function update(UpdateTestModelRequest $request, int $id): TestResource
    {
        $testModel = TestModel::query()->findOrFail($id);
        $testModel->update($request->validated());

        return new TestResource($testModel);
    }

    #[DeleteEndpoint(
        path: '/api/v1/test-models/{id}',
        description: 'delete test resource',
    )]
    public function delete(int $id): Response
    {
        $testModel = TestModel::query()->findOrFail($id);
        $testModel->delete();

        return response()->noContent();
    }

    #[ActionEndpoint(
        path: '/api/v1/test-models/{id}/actions/test',
        description: 'Execute test action on test resource',
    )]
    public function testAction(int $id): Response
    {
        return response()->noContent();
    }
}
