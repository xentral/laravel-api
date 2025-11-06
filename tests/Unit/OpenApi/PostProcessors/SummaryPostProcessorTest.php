<?php declare(strict_types=1);

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\PostProcessors\SummaryPostProcessor;

it('sets default summary to description with version suffix when summary is not provided', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = 'List test resources';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('List test resources V1');
});

it('preserves explicit summary when provided and adds version suffix', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->summary = 'Custom summary for endpoint';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('Custom summary for endpoint V1');
});

it('prepends lock emoji to summary when operation has feature flag', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = 'List test resources';
    $operation->x = ['feature_flag' => 'beta-users'];

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('🔒 List test resources V1');
});

it('prepends lock emoji to explicit summary when operation has feature flag', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->summary = 'Custom endpoint summary';
    $operation->x = ['feature_flag' => 'beta-users'];

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('🔒 Custom endpoint summary V1');
});

it('processes multiple operations correctly', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation1 = new OA\Get(['path' => '/api/v1/test-models']);
    $operation1->summary = Generator::UNDEFINED;
    $operation1->description = 'List test resources';

    $operation2 = new OA\Post(['path' => '/api/v1/test-models']);
    $operation2->summary = 'Create a test model';
    $operation2->x = ['feature_flag' => 'beta-users'];

    $analysis->addAnnotation($operation1, new Context);
    $analysis->addAnnotation($operation2, new Context);

    $processor($analysis);

    expect($operation1->summary)->toBe('List test resources V1')
        ->and($operation2->summary)->toBe('🔒 Create a test model V1');
});

it('extracts and suffixes version V2 from path', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v2/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = 'List test resources';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('List test resources V2');
});

it('extracts and suffixes version V3 from path', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v3/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = 'List test resources';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('List test resources V3');
});

it('handles path without version gracefully', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = 'List test resources';

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('List test resources');
});

it('falls back to path when description is undefined', function () {
    $processor = new SummaryPostProcessor;
    $analysis = new Analysis([], new Context);

    $operation = new OA\Get(['path' => '/api/v1/test-models']);
    $operation->summary = Generator::UNDEFINED;
    $operation->description = Generator::UNDEFINED;

    $analysis->addAnnotation($operation, new Context);

    $processor($analysis);

    expect($operation->summary)->toBe('/api/v1/test-models V1');
});
