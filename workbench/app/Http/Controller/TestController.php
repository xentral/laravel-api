<?php declare(strict_types=1);
namespace Workbench\App\Http\Controller;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Workbench\App\Http\Filter\FilterCollection;
use Workbench\App\Http\Requests\CreateTestModelRequest;
use Workbench\App\Http\Requests\UpdateTestModelRequest;
use Workbench\App\Http\Resources\TestResource;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\OpenApi\Endpoints\ActionEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\DeleteEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\PatchEndpoint;
use Xentral\LaravelApi\OpenApi\Endpoints\PostEndpoint;
use Workbench\App\Enum\StatusEnum;
use Xentral\LaravelApi\OpenApi\Filters\EnumFilter;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\Filters\IdFilter;
use Xentral\LaravelApi\OpenApi\Filters\StringFilter;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\Query\Filters\QueryFilter;
use Xentral\LaravelApi\Query\QueryBuilder;

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
                new EnumFilter(name: 'status', enumSource: StatusEnum::class),
                new FilterCollection,
            ]),
        ],
        maxPageSize: 1337,
        featureFlag: 'beta-users',
        scopes: 'test-models:read',
    )]
    public function index(): ResourceCollection
    {
        return TestResource::collection(
            QueryBuilder::for(TestModel::class)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('name'),
                    QueryFilter::string('status'),
                    new FilterCollection,
                )
                ->apiPaginate()
        );
    }

    #[ListEndpoint(
        path: '/api/v1/test-models-multi-pagination',
        resource: TestResource::class,
        description: 'List test resources with multiple pagination options',
        includes: ['test model'],
        parameters: [
            new FilterParameter([
                new IdFilter,
                new StringFilter(name: 'name'),
                new EnumFilter(name: 'status', enumSource: StatusEnum::class),
            ]),
        ],
        paginationType: [PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR],
    )]
    public function indexMultiPagination(): ResourceCollection
    {
        return TestResource::collection(
            QueryBuilder::for(TestModel::class)
                ->allowedFilters(
                    QueryFilter::identifier(),
                    QueryFilter::string('name'),
                    QueryFilter::string('status'),
                )
                ->apiPaginate(100, PaginationType::SIMPLE, PaginationType::TABLE, PaginationType::CURSOR)
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

    #[GetEndpoint(
        path: '/api/v1/test-models/{id}/legacy',
        resource: TestResource::class,
        description: 'Legacy endpoint for test resource',
        deprecated: new \DateTime('2025-07-01'),
    )]
    public function legacyShow(int $id): TestResource
    {
        return new TestResource(TestModel::query()->findOrFail($id));
    }
}
