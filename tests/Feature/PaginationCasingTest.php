<?php declare(strict_types=1);

it('returns snake_case pagination by default', function () {
    config(['openapi.schemas.default.config.pagination_response.casing' => 'snake']);

    $response = $this->getJson('/api/v1/test-models');

    $response->assertOk();
    $data = $response->json();

    // Should have snake_case pagination keys
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('current_page')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->toHaveKey('path')
        ->and($data['meta'])->toHaveKey('from')
        ->and($data['meta'])->toHaveKey('to');
});

it('returns camelCase pagination when configured', function () {
    config(['openapi.schemas.default.config.pagination_response.casing' => 'camel']);

    $response = $this->getJson('/api/v1/test-models');

    $response->assertOk();
    $data = $response->json();

    // Should have camelCase pagination keys
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('currentPage')
        ->and($data['meta'])->toHaveKey('perPage')
        ->and($data['meta'])->toHaveKey('path')
        ->and($data['meta'])->toHaveKey('from')
        ->and($data['meta'])->toHaveKey('to')
        ->and($data['meta'])->not->toHaveKey('current_page')
        ->and($data['meta'])->not->toHaveKey('per_page');
});
