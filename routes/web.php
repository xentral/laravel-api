<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Xentral\LaravelApi\Http\ApiDocsController;

if (config('openapi.docs.enabled') === false) {
    return;
}
Route::middleware(config('openapi.docs.middleware', []))
    ->prefix(config('openapi.docs.prefix', 'api-docs'))
    ->group(function () {
        Route::get('/assets/{asset}', [ApiDocsController::class, 'assets'])->name('openapi.docs.assets');
        Route::get('/schemas/{schema}', [ApiDocsController::class, 'schema'])->name('openapi.schema');
        Route::get('/{schema?}', [ApiDocsController::class, 'docs'])->name('openapi.docs');
    });
