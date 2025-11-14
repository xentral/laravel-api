<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Generator;

class SortOperationsProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        if ($analysis->openapi->paths === Generator::UNDEFINED || ! is_iterable($analysis->openapi->paths)) {
            return;
        }

        // Convert to array, sort by path, and reassign
        $paths = iterator_to_array($analysis->openapi->paths);

        usort(
            $paths,
            fn ($a, $b) => strcmp((string) $a->path, (string) $b->path),
        );

        $analysis->openapi->paths = $paths;
    }
}
