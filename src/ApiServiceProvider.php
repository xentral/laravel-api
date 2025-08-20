<?php declare(strict_types=1);
namespace Xentral\LaravelApi;

use Illuminate\Support\ServiceProvider;
use Xentral\LaravelApi\Http\QueryBuilderRequest;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__).'/.ai/guidelines/how-to-write-apis.blade.php' => base_path('.ai/guidelines/how-to-write-apis.blade.php'),
        ], 'xentral-testing');
        $this->mergeConfigFrom(__DIR__.'/../config/openapi.php', 'openapi');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'openapi');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->bind(QueryBuilderRequest::class, fn ($app) => QueryBuilderRequest::fromRequest($app['request']));
    }

    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\GenerateOpenApiSpecCommand::class,
            ]);
        }
    }
}
