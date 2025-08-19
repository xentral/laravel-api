<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use Illuminate\Support\Carbon;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

class FilterDeprecationsProcessor
{
    public function __construct(public int $monthBeforeRemoval = 6) {}

    public function __invoke(Analysis $analysis)
    {
        /** @var OA\PathItem $path */
        foreach ($analysis->openapi->paths as $index => $path) {
            foreach ($path->operations() as $operation) {
                if ($operation->deprecated !== Generator::UNDEFINED && $operation->deprecated === true) {
                    $date = Carbon::createFromFormat('Y-m-d', $operation->x['deprecated_on'] ?? 'now');
                    // compare the deprecation date with the current date minus the monthBeforeRemoval
                    if ($date->addMonths($this->monthBeforeRemoval)->isBefore(now())) {
                        // Remove deprecated operations that are past the removal date
                        $path->{$operation->method} = Generator::UNDEFINED;
                    }
                }
            }
            if (empty($path->operations())) {
                // Remove empty paths
                unset($analysis->openapi->paths[$index]);
            }
        }
    }
}
