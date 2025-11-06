<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class SummaryPostProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (Generator::isDefault($operation->summary)) {
                $operation->summary = ! Generator::isDefault($operation->description)
                    ? $operation->description
                    : $operation->path;
            }

            if ($version = $this->extractVersion($operation->path)) {
                $operation->summary .= ' '.$version;
            }

            // If operation is behind a feature flag, prepend lock emoji
            if (isset($operation->x['feature_flag'])) {
                $operation->summary = '🔒 '.$operation->summary;
            }
        }
    }

    private function extractVersion(string $path): ?string
    {
        if (preg_match('#/v(\d+)/#i', $path, $matches)) {
            return 'V'.strtoupper($matches[1]);
        }

        return null;
    }
}
