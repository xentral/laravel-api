<?php declare(strict_types=1);
namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Boost\Install\GuidelineComposer;
use Workbench\App\PackageGuidelineComposer;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(GuidelineComposer::class, PackageGuidelineComposer::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        config()->set('cache.default', 'array');
        config()->set('testing.openapi.schema_base_path', '/foo');
    }
}
