<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;
use OpenApi\Loggers\ConsoleLogger;
use OpenApi\Processors\CleanUnusedComponents;
use OpenApi\Processors\ExpandEnums;
use OpenApi\Processors\OperationId;
use Xentral\LaravelApi\OpenApi\PostProcessors\AddMetaInfoProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\CustomCleanUnusedComponents;
use Xentral\LaravelApi\OpenApi\PostProcessors\DeprecationsProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\FeatureFlagProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\OperationIdProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\PaginationResponseProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\ProblemsProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\RateLimitResponseProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\SortComponentsProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\TokenScopeProcessor;
use Xentral\LaravelApi\OpenApi\PostProcessors\ValidationResponseProcessor;
use Xentral\LaravelApi\OpenApi\Processors\ExpandEnumsWithInactiveSupport;

class OpenApiGeneratorFactory
{
    public function create(array $config): Generator
    {
        $schemaDefinition = SchemaDefinition::fromArray($config);

        $generator = new Generator(new ConsoleLogger);
        $generator->getProcessorPipeline()->insert(new AddMetaInfoProcessor($schemaDefinition->info, $config['exclude_money'] ?? false), fn () => 1);
        $generator->getProcessorPipeline()->remove(ExpandEnums::class);
        $generator->getProcessorPipeline()->insert(new ExpandEnumsWithInactiveSupport, fn () => 5); // Insert at same position as original ExpandEnums
        $generator->getProcessorPipeline()->remove(OperationId::class);
        $generator->getProcessorPipeline()->remove(CleanUnusedComponents::class);
        // Add our custom CleanUnusedComponents that preserves security schemes
        $generator->getProcessorPipeline()->add(new CustomCleanUnusedComponents);
        $generator->getProcessorPipeline()->add(new OperationIdProcessor);
        $generator->getProcessorPipeline()->add(new TokenScopeProcessor);
        $generator->getProcessorPipeline()->add(new ValidationResponseProcessor($schemaDefinition->config));
        $generator->getProcessorPipeline()->add(new PaginationResponseProcessor($schemaDefinition->config));
        $generator->getProcessorPipeline()->add(new RateLimitResponseProcessor($schemaDefinition->config));
        $generator->getProcessorPipeline()->add(new ProblemsProcessor);
        $generator->getProcessorPipeline()->add(new FeatureFlagProcessor($schemaDefinition->config));
        $generator->getProcessorPipeline()->add(new SortComponentsProcessor);
        if ($schemaDefinition->config->deprecationFilter->enabled) {
            $generator->getProcessorPipeline()->add(new DeprecationsProcessor($schemaDefinition->config));
        }

        $analyzer = new ReflectionAnalyser([new DocBlockAnnotationFactory, new AttributeAnnotationFactory]);

        return $generator
            ->setVersion($schemaDefinition->config->oasVersion)
            ->setAnalyser($analyzer);
    }
}
