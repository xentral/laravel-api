<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('adds sunset header to deprecated endpoints within the removal window', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    $deprecatedEndpoint = $data['paths']['/api/v1/customers/{id}/legacy']['get'] ?? null;

    expect($deprecatedEndpoint)->not->toBeNull()
        ->and($deprecatedEndpoint['deprecated'])->toBeTrue();

    $response200 = $deprecatedEndpoint['responses']['200'];

    expect($response200['headers'])->toHaveKey('Sunset')
        ->and($response200['headers']['Sunset']['schema']['type'])->toBe('string')
        ->and($response200['headers']['Sunset']['schema']['format'])->toBe('http-date')
        ->and($response200['headers']['Sunset']['schema']['example'])->toMatch('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/');
});

it('calculates sunset date as deprecation date plus configured months', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    $deprecatedEndpoint = $data['paths']['/api/v1/customers/{id}/legacy']['get'];
    $sunsetExample = $deprecatedEndpoint['responses']['200']['headers']['Sunset']['schema']['example'];

    // CustomerController has deprecated: 2025-11-01, config has months_before_removal: 6
    // So sunset should be 2026-05-01
    expect($sunsetExample)->toContain('01 May 2026');
});

it('removes deprecated endpoints past the removal window', function () {
    \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::parse('2026-06-01'));

    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    // The legacy endpoint (deprecated 2025-11-01, sunset 2026-05-01) should be removed
    // because we're now in June 2026
    expect($data['paths']['/api/v1/customers/{id}/legacy'] ?? null)->toBeNull();

    \Illuminate\Support\Facades\Date::setTestNow();
});

it('does not add sunset header to non-deprecated endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    $regularEndpoint = $data['paths']['/api/v1/invoices/{id}']['get'];

    expect($regularEndpoint['deprecated'] ?? false)->toBeFalse();
    expect($regularEndpoint['responses']['200']['headers']['Sunset'] ?? null)->toBeNull();
});
