<?php declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;
use Xentral\LaravelApi\OpenApi\OpenApiGeneratorFactory;

it('adds content negotiation note when 200 response has multiple media types', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    $invoiceEndpoint = $data['paths']['/api/v1/invoices/{id}']['get'];

    expect($invoiceEndpoint['description'])
        ->toContain('Content Negotiation')
        ->toContain('`application/pdf`')
        ->toContain('`Accept` header');
});

it('does not add content negotiation note when only one media type', function () {
    $factory = new OpenApiGeneratorFactory;
    $generator = $factory->create(config('openapi.schemas.default'));

    $spec = $generator->generate([workbench_dir()]);
    $data = Yaml::parse($spec->toYaml());

    // Customer endpoint has only JSON response
    $customerEndpoint = $data['paths']['/api/v1/customers/{id}']['get'];

    expect($customerEndpoint['description'])->not->toContain('Content Negotiation');
});
