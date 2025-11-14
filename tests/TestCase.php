<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Workbench\App\Http\Controller\CustomerController;
use Workbench\App\Http\Controller\InvoiceController;
use Workbench\App\Http\Controller\LineItemController;
use Workbench\App\Http\Controller\TestController;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Xentral\LaravelApi\ApiServiceProvider;
use Xentral\LaravelApi\Http\QueryBuilderRequest;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QueryBuilderRequest::resetDelimiters();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Workbench\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }

    protected function defineWebRoutes($router): void
    {
        $router->get('/api/v1/test-models', [TestController::class, 'index']);
        $router->get('/api/v1/test-models-multi-pagination', [TestController::class, 'indexMultiPagination']);
        $router->get('/api/v1/test-models/{id}', [TestController::class, 'show']);
        $router->get('/api/v1/test-models/{id}/legacy', [TestController::class, 'legacyShow']);
        $router->post('/api/v1/test-models', [TestController::class, 'create']);
        $router->patch('/api/v1/test-models/{id}', [TestController::class, 'update']);
        $router->delete('/api/v1/test-models/{id}', [TestController::class, 'delete']);
        $router->patch('/api/v1/test-models/{id}/actions/test', [TestController::class, 'testAction']);

        // Customer routes
        $router->get('/api/v1/customers', [CustomerController::class, 'index']);
        $router->get('/api/v1/customers/{id}', [CustomerController::class, 'show']);

        // Invoice routes
        $router->get('/api/v1/invoices', [InvoiceController::class, 'index']);
        $router->get('/api/v1/invoices/{id}', [InvoiceController::class, 'show']);

        // LineItem routes (nested under invoices)
        $router->get('/api/v1/invoices/{id}/line-items', [LineItemController::class, 'index']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApiServiceProvider::class,
            LaravelDataServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }
}
