<?php declare(strict_types=1);

it('schema method returns JSON for existing schema', function () {
    $this->withoutMiddleware()->get('/api-docs/schemas/default')->assertOk();
});

it('schema method returns 404 for non-existent schema', function () {
    $this->withoutMiddleware()->get('/api-docs/schemas/wdqdq')->assertNotFound();
});

it('docs method returns correct view', function () {
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewIs('openapi::docs');
});

it('updates server URL in schema response', function () {
    config(['app.url' => 'https://test-server.com']);
    $this->withoutMiddleware()->get('/api-docs/schemas/default')->assertJsonPath('servers.0.url', 'https://test-server.com');
});
