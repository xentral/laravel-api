<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use Illuminate\Support\Str;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\SchemaConfig;

class FeatureFlagProcessor
{
    public function __construct(private readonly SchemaConfig $config) {}

    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (isset($operation->x['feature_flag'])) {
                $description = $operation->description !== Generator::UNDEFINED ? $operation->description : '';
                $operation->description = Str::of($description)
                    ->prepend(str_replace('{flag}', $operation->x['feature_flag'], $this->config->featureFlags->descriptionPrefix))
                    ->toString();
            }
        }
    }
}
