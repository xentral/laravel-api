<?php declare(strict_types=1);

it('schema method returns JSON for existing schema', function () {
    $this->withoutMiddleware()->get('/api-docs/schemas/default')->assertOk();
});

it('schema method returns 404 for non-existent schema', function () {
    $this->withoutMiddleware()->get('/api-docs/schemas/wdqdq')->assertNotFound();
});

it('updates server URL in schema response', function () {
    config(['app.url' => 'https://test-server.com']);
    $this->withoutMiddleware()->get('/api-docs/schemas/default')->assertJsonPath('servers.0.url', 'https://test-server.com');
});

it('uses scalar view when configured globally', function () {
    config(['openapi.docs.client' => 'scalar']);
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewIs('openapi::scalar');
});

it('uses scalar view when configured per-schema', function () {
    config(['openapi.schemas.default.client' => 'scalar']);
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewIs('openapi::scalar');
});

it('per-schema client setting overrides global setting', function () {
    config(['openapi.docs.client' => 'swagger']);
    config(['openapi.schemas.default.client' => 'scalar']);
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewIs('openapi::scalar');
});

it('query parameter overrides config settings', function () {
    config(['openapi.docs.client' => 'swagger']);
    config(['openapi.schemas.default.client' => 'swagger']);
    $this->withoutMiddleware()->get('/api-docs/default?client=scalar')->assertViewIs('openapi::scalar');
});

it('query parameter can switch to swagger from scalar config', function () {
    config(['openapi.docs.client' => 'scalar']);
    $this->withoutMiddleware()->get('/api-docs/default?client=swagger')->assertViewIs('openapi::docs');
});

it('defaults to swagger for invalid client types', function () {
    config(['openapi.docs.client' => 'invalid']);
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewIs('openapi::docs');
});

it('defaults to swagger for invalid query parameter', function () {
    $this->withoutMiddleware()->get('/api-docs/default?client=invalid')->assertViewIs('openapi::docs');
});

it('passes client type to view data', function () {
    config(['openapi.docs.client' => 'scalar']);
    $this->withoutMiddleware()->get('/api-docs/default')->assertViewHas('client', 'scalar');
});
