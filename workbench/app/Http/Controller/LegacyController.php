<?php declare(strict_types=1);

namespace Workbench\App\Http\Controller;

use Workbench\App\Http\Resources\TestResource;
use Workbench\App\Models\TestModel;
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;

class LegacyController
{
    #[GetEndpoint(
        path: '/api/legacy',
        resource: TestResource::class,
        description: 'deprecated legacy endpoint',
        deprecated: new \DateTimeImmutable('2024-01-01'),
    )]
    public function show(int $id): TestResource
    {
        return new TestResource(TestModel::query()->findOrFail($id));
    }
}
