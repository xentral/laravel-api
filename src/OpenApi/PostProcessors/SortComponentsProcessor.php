<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;

class SortComponentsProcessor
{
    public function __invoke(Analysis $analysis)
    {
        if (is_object($analysis->openapi->components) && is_iterable($analysis->openapi->components->schemas)) {
            usort(
                $analysis->openapi->components->schemas,
                fn ($a, $b) => strcmp((string) $a->schema, (string) $b->schema),
            );
        }
    }
}
