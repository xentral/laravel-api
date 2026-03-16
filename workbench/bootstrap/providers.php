<?php declare(strict_types=1);
use Laravel\Boost\BoostServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;
use Xentral\LaravelApi\ApiServiceProvider;
use Xentral\LaravelTesting\TestingServiceProvider;

return [
    TestingServiceProvider::class,
    WorkbenchServiceProvider::class,
    BoostServiceProvider::class,
    ApiServiceProvider::class,
    McpServiceProvider::class,
];
