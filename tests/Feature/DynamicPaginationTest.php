<?php declare(strict_types=1);

it('defaults to simple pagination when no header is provided', function () {
    $response = $this->getJson('/api/v1/test-models-multi-pagination');

    $response->assertOk();
    $data = $response->json();

    // Simple pagination should have 'links' with prev/next and 'meta' without last_page
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('current_page')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->not->toHaveKey('last_page') // Simple pagination doesn't have last_page
        ->and($data)->toHaveKey('links')
        ->and($data['links'])->toHaveKey('first')
        ->and($data['links'])->toHaveKey('next');
});

it('uses simple pagination when x-pagination header is set to simple', function () {
    $response = $this->getJson('/api/v1/test-models-multi-pagination', [
        'x-pagination' => 'simple',
    ]);

    $response->assertOk();
    $data = $response->json();

    // Simple pagination should have 'links' with prev/next and 'meta' without last_page
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('current_page')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->not->toHaveKey('last_page'); // Simple pagination doesn't have last_page
});

it('uses table pagination when x-pagination header is set to table', function () {
    $response = $this->getJson('/api/v1/test-models-multi-pagination', [
        'x-pagination' => 'table',
    ]);

    $response->assertOk();
    $data = $response->json();

    // Table pagination should have 'links' and 'meta' with last_page
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('current_page')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->toHaveKey('last_page') // Table pagination has last_page
        ->and($data['meta'])->toHaveKey('total') // Table pagination has total
        ->and($data)->toHaveKey('links')
        ->and($data['links'])->toHaveKey('first')
        ->and($data['links'])->toHaveKey('last');
});

it('uses cursor pagination when x-pagination header is set to cursor', function () {
    $response = $this->getJson('/api/v1/test-models-multi-pagination', [
        'x-pagination' => 'cursor',
    ]);

    $response->assertOk();
    $data = $response->json();

    // Cursor pagination should have different structure
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->toHaveKey('next_cursor') // Cursor pagination has next_cursor
        ->and($data['meta'])->toHaveKey('prev_cursor') // Cursor pagination has prev_cursor
        ->and($data['meta'])->not->toHaveKey('current_page') // Cursor pagination doesn't have current_page
        ->and($data['meta'])->not->toHaveKey('total'); // Cursor pagination doesn't have total
});

it('falls back to first allowed type when requesting unsupported pagination type', function () {
    // Test endpoint only supports SIMPLE pagination
    $response = $this->getJson('/api/v1/test-models', [
        'x-pagination' => 'table', // This should fall back to simple
    ]);

    $response->assertOk();
    $data = $response->json();

    // Should fall back to simple pagination (first allowed type)
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('current_page')
        ->and($data['meta'])->toHaveKey('per_page')
        ->and($data['meta'])->not->toHaveKey('last_page'); // Simple pagination doesn't have last_page
});

it('is case insensitive for pagination header values', function () {
    $response = $this->getJson('/api/v1/test-models-multi-pagination', [
        'x-pagination' => 'TABLE', // Uppercase should work
    ]);

    $response->assertOk();
    $data = $response->json();

    // Should use table pagination
    expect($data)->toHaveKey('meta')
        ->and($data['meta'])->toHaveKey('last_page'); // Table pagination has last_page
});
