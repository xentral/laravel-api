<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('adds 429 response to all endpoints when enabled', function () {
    $config = config('openapi.schemas.default');
    $config['config']['rate_limit_response'] = [
        'enabled' => true,
        'message' => 'Rate limit exceeded',
    ];

    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create($config);

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check for 429 responses
    $data = Yaml::parse($yaml);

    // Check a regular endpoint
    $endpoint = $data['paths']['/api/v1/test-models']['get'];

    expect($endpoint['responses'])->toHaveKey('429')
        ->and($endpoint['responses']['429']['description'])->toBe('Rate limit exceeded')
        ->and($endpoint['responses']['429']['content']['application/json']['schema']['properties']['message']['type'])->toBe('string')
        ->and($endpoint['responses']['429']['content']['application/json']['schema']['properties']['message']['example'])->toBe('Rate limit exceeded');
});

it('does not add 429 response when disabled', function () {
    $config = config('openapi.schemas.default');
    $config['config']['rate_limit_response'] = [
        'enabled' => false,
        'message' => 'Rate limit exceeded',
    ];

    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create($config);

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check for absence of 429 responses
    $data = Yaml::parse($yaml);

    // Check a regular endpoint
    $endpoint = $data['paths']['/api/v1/test-models']['get'];

    expect($endpoint['responses'])->not->toHaveKey('429');
});

it('uses custom message from config', function () {
    $customMessage = 'Too many requests, please slow down';

    $config = config('openapi.schemas.default');
    $config['config']['rate_limit_response'] = [
        'enabled' => true,
        'message' => $customMessage,
    ];

    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create($config);

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check for custom message
    $data = Yaml::parse($yaml);

    // Check a regular endpoint
    $endpoint = $data['paths']['/api/v1/test-models']['get'];

    expect($endpoint['responses']['429']['description'])->toBe($customMessage)
        ->and($endpoint['responses']['429']['content']['application/json']['schema']['properties']['message']['example'])->toBe($customMessage);
});

it('adds 429 response to POST endpoints', function () {
    $config = config('openapi.schemas.default');
    $config['config']['rate_limit_response'] = [
        'enabled' => true,
        'message' => 'Rate limit exceeded',
    ];

    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create($config);

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check for 429 responses on POST endpoints
    $data = Yaml::parse($yaml);

    // Check POST endpoint
    $endpoint = $data['paths']['/api/v1/test-models']['post'];

    expect($endpoint['responses'])->toHaveKey('429')
        ->and($endpoint['responses']['429']['description'])->toBe('Rate limit exceeded');
});
