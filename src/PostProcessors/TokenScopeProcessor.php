<?php declare(strict_types=1);

namespace Xentral\LaravelApi\PostProcessors;

use Illuminate\Support\Str;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class TokenScopeProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (isset($operation->x['scopes']) && ! empty($operation->x['scopes'])) {
                $scopes = array_map(
                    fn ($scope) => "`{$scope}`",
                    $operation->x['scopes'],
                );
                $description = $operation->description !== Generator::UNDEFINED ? $operation->description : '';
                $operation->description = Str::of($description)
                    ->prepend('This endpoint requires the following scopes: '.implode(', ', $scopes).".\n\n")
                    ->toString();
            }
        }
    }
}
