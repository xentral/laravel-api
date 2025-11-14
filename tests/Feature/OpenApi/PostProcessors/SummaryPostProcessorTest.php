<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('generates summary from description with version suffix when no summary is provided', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    // Check endpoint without explicit summary (using Invoice)
    $getEndpoint = $data['paths']['/api/v1/invoices/{id}']['get'] ?? null;

    expect($getEndpoint)->not->toBeNull()
        ->and($getEndpoint['summary'])->toEndWith('V1');
});

it('prepends lock emoji to summary for feature flagged endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    // Check the list endpoint which has featureFlag: 'beta-customers'
    $listEndpoint = $data['paths']['/api/v1/customers']['get'] ?? null;

    expect($listEndpoint)->not->toBeNull()
        ->and($listEndpoint['summary'])->toStartWith('🔒 ')
        ->and($listEndpoint['summary'])->toEndWith('V1');
});

it('uses description with version suffix for non-feature-flagged endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    // Check endpoints without feature flags (using Invoice)
    $getEndpoint = $data['paths']['/api/v1/invoices']['get'] ?? null;

    expect($getEndpoint)->not->toBeNull()
        ->and($getEndpoint['summary'])->toEndWith('V1')
        ->and($getEndpoint['summary'])->not->toStartWith('🔒 ');
});

it('applies summary processing to all HTTP methods', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    $invoicesPath = $data['paths']['/api/v1/invoices'] ?? null;
    $invoiceDetailPath = $data['paths']['/api/v1/invoices/{id}'] ?? null;

    expect($invoicesPath)->not->toBeNull()
        ->and($invoiceDetailPath)->not->toBeNull();

    // Check that GET endpoints have summaries with version suffix
    expect($invoicesPath['get']['summary'])->toBeString()->not->toBeEmpty()->toEndWith('V1')
        ->and($invoiceDetailPath['get']['summary'])->toBeString()->not->toBeEmpty()->toEndWith('V1');
});
