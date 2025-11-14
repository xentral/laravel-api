<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('adds sunset header to deprecated endpoints that are still active', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    // Parse YAML to check for sunset headers
    $data = Yaml::parse($yaml);

    // Check if our deprecated endpoint exists (it should since it's within the removal window)
    $deprecatedEndpoint = $data['paths']['/api/v1/customers/{id}/legacy']['get'] ?? null;

    expect($deprecatedEndpoint)->not->toBeNull()
        ->and($deprecatedEndpoint['deprecated'])->toBeTrue();

    // Check that the 200 response has the sunset header
    $response200 = $deprecatedEndpoint['responses']['200'];

    expect($response200['headers'])->toHaveKey('Sunset')
        ->and($response200['headers']['Sunset']['description'])->toBe('This endpoint is deprecated and will be sunset on the example date. If an endpoint is over this date but still accessible, it can be removed any time.')
        ->and($response200['headers']['Sunset']['schema']['type'])->toBe('string')
        ->and($response200['headers']['Sunset']['schema']['format'])->toBe('http-date')
        ->and($response200['headers']['Sunset']['schema']['example'])->toMatch('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/');
});

it('calculates sunset date correctly based on deprecation date and months before removal', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    // Parse YAML to check sunset date calculation
    $data = Yaml::parse($yaml);

    $deprecatedEndpoint = $data['paths']['/api/v1/customers/{id}/legacy']['get'];
    $sunsetExample = $deprecatedEndpoint['responses']['200']['headers']['Sunset']['schema']['example'];

    // The sunset date should be deprecation date (2025-07-01) + 6 months = 2026-01-01
    $expectedDatePattern = '01 Jan 2026';

    expect($sunsetExample)->toContain($expectedDatePattern);
});

it('adds sunset header to all response types for deprecated endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    // Parse YAML to check that all responses have sunset header
    $data = Yaml::parse($yaml);

    $deprecatedEndpoint = $data['paths']['/api/v1/customers/{id}/legacy']['get'];

    // Check that each response has the sunset header
    foreach ($deprecatedEndpoint['responses'] as $statusCode => $response) {
        if (isset($response['headers'])) {
            expect($response['headers'])->toHaveKey('Sunset');
        }
    }
});

it('does not add sunset header to non-deprecated endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    // Parse YAML to check that non-deprecated endpoints don't have sunset headers
    $data = Yaml::parse($yaml);

    // Check a regular non-deprecated endpoint (using Invoice endpoint)
    $regularEndpoint = $data['paths']['/api/v1/invoices/{id}']['get'];

    expect($regularEndpoint['deprecated'] ?? false)->toBeFalse();

    // Check that responses don't have sunset headers
    $response200 = $regularEndpoint['responses']['200'];
    expect($response200['headers']['Sunset'] ?? null)->toBeNull();
});
