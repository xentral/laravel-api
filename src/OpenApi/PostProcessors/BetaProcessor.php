<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use Illuminate\Support\Str;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class BetaProcessor
{
    private const BETA_DESCRIPTION = 'This endpoint is currently in Beta and available for testing. It may contain bugs, and breaking changes can occur at any time without prior notice. We do not recommend using Beta endpoints in production environments. Should you choose to use it in production, you assume full responsibility for any resulting issues.';

    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (isset($operation->x['beta'])) {
                $description = $operation->description !== Generator::UNDEFINED ? $operation->description : '';
                $operation->description = Str::of($description)
                    ->prepend(self::BETA_DESCRIPTION."\n\n")
                    ->toString();
            }
        }
    }
}
