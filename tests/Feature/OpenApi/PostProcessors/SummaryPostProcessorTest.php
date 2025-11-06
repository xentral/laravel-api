<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('generates summary from description with version suffix when no summary is provided', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    // Check endpoint without explicit summary
    $getEndpoint = $data['paths']['/api/v1/test-models/{id}']['get'] ?? null;

    expect($getEndpoint)->not->toBeNull()
        ->and($getEndpoint['summary'])->toBe('get test resource V1');
});

it('prepends lock emoji to summary for feature flagged endpoints', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    // Check the list endpoint which has featureFlag: 'beta-users'
    $listEndpoint = $data['paths']['/api/v1/test-models']['get'] ?? null;

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

    // Check endpoints without feature flags
    $deleteEndpoint = $data['paths']['/api/v1/test-models/{id}']['delete'] ?? null;

    expect($deleteEndpoint)->not->toBeNull()
        ->and($deleteEndpoint['summary'])->toBe('delete test resource V1')
        ->and($deleteEndpoint['summary'])->not->toStartWith('🔒 ');
});

it('applies summary processing to all HTTP methods', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $yaml = $spec->toYaml();

    $data = Yaml::parse($yaml);

    $testModelsPath = $data['paths']['/api/v1/test-models/{id}'] ?? null;

    expect($testModelsPath)->not->toBeNull();

    // Check that GET, PATCH, DELETE all have summaries with version suffix
    expect($testModelsPath['get']['summary'])->toBeString()->not->toBeEmpty()->toEndWith('V1')
        ->and($testModelsPath['patch']['summary'])->toBeString()->not->toBeEmpty()->toEndWith('V1')
        ->and($testModelsPath['delete']['summary'])->toBeString()->not->toBeEmpty()->toEndWith('V1');
});
