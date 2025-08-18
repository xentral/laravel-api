<?php declare(strict_types=1);

namespace Xentral\LaravelApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;

class ValidationResponseProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (isset($operation->x['request'])) {
                // Here we can generate a response for validation errors
                unset($operation->x['request']);
            }
        }
    }
}
