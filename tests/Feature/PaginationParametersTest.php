<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('generates correct parameters for single pagination type', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check parameters
    $data = Yaml::parse($yaml);

    // Check single pagination endpoint (only supports simple)
    $singleEndpoint = $data['paths']['/api/v1/test-models']['get'];

    // Should have per_page and page parameters (simple pagination)
    $paramNames = array_column($singleEndpoint['parameters'], 'name');

    expect($paramNames)->toContain('per_page')
        ->and($paramNames)->toContain('page')
        ->and($paramNames)->not->toContain('cursor'); // Should not have cursor for simple only
});

it('generates correct parameters for multiple pagination types', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check parameters
    $data = Yaml::parse($yaml);

    // Check multi-pagination endpoint (supports simple, table, cursor)
    $multiEndpoint = $data['paths']['/api/v1/test-models-multi-pagination']['get'];

    // Should have all parameters since it supports all types
    $paramNames = array_column($multiEndpoint['parameters'], 'name');

    expect($paramNames)->toContain('per_page')
        ->and($paramNames)->toContain('page')
        ->and($paramNames)->toContain('cursor'); // Should have all parameters
});

it('parameter descriptions explain when they are used', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([__DIR__.'/../../workbench']);
    $yaml = $spec->toYaml();

    // Parse YAML to check parameter descriptions
    $data = Yaml::parse($yaml);

    // Check multi-pagination endpoint descriptions
    $multiEndpoint = $data['paths']['/api/v1/test-models-multi-pagination']['get'];
    $parameters = $multiEndpoint['parameters'];

    // Find cursor parameter
    $cursorParam = collect($parameters)->firstWhere('name', 'cursor');
    expect($cursorParam['description'])->toContain('cursor pagination');

    // Find page parameter
    $pageParam = collect($parameters)->firstWhere('name', 'page');
    expect($pageParam['description'])->toContain('simple and table pagination');
});
