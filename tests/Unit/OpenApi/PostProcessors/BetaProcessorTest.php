<?php declare(strict_types=1);

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\PostProcessors\BetaProcessor;

it('prepends beta disclaimer to description when beta flag is set', function () {
    $processor = new BetaProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->description = 'List test resources';
    $operation->x = ['beta' => true];

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    $expectedPrefix = 'This endpoint is currently in Beta and available for testing. It may contain bugs, and breaking changes can occur at any time without prior notice. We do not recommend using Beta endpoints in production environments. Should you choose to use it in production, you assume full responsibility for any resulting issues.';

    expect($operation->description)->toBe($expectedPrefix."\n\n".'List test resources');
});

it('does not modify description when beta flag is not set', function () {
    $processor = new BetaProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->description = 'List test resources';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->description)->toBe('List test resources');
});

it('handles undefined description when beta flag is set', function () {
    $processor = new BetaProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->description = Generator::UNDEFINED;
    $operation->x = ['beta' => true];

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    $expectedPrefix = 'This endpoint is currently in Beta and available for testing. It may contain bugs, and breaking changes can occur at any time without prior notice. We do not recommend using Beta endpoints in production environments. Should you choose to use it in production, you assume full responsibility for any resulting issues.';

    expect($operation->description)->toBe($expectedPrefix."\n\n");
});
