<?php declare(strict_types=1);

namespace Xentral\LaravelApi;

use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/openapi.php', 'openapi');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'openapi');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->bind(QueryBuilderRequest::class, fn ($app) => QueryBuilderRequest::fromRequest($app['request']));
    }

    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\GenerateOpenApiSpecCommand::class,
            ]);
        }
    }
}
