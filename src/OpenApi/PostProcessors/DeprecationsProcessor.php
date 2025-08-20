<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\Header;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\SchemaConfig;

class DeprecationsProcessor
{
    public function __construct(private readonly SchemaConfig $config) {}

    public function __invoke(Analysis $analysis)
    {
        /** @var OA\PathItem $path */
        foreach ($analysis->openapi->paths as $index => $path) {
            foreach ($path->operations() as $operation) {
                if ($operation->deprecated !== Generator::UNDEFINED && $operation->deprecated === true) {
                    $deprecatedOn = $operation->x['deprecated_on'] ?? 'now';
                    if ($this->makeSunsetDate($deprecatedOn)->isBefore(now())) {
                        // Remove deprecated operations that are past the removal date
                        $path->{$operation->method} = Generator::UNDEFINED;
                    } else {
                        // Add sunset header to responses for deprecated operations still active
                        $this->addSunsetHeaderToResponses($operation, $this->makeSunsetDate($deprecatedOn));
                    }
                    unset($operation->x['deprecated_on']);
                }
            }
            if (empty($path->operations())) {
                // Remove empty paths
                unset($analysis->openapi->paths[$index]);
            }
        }
    }

    private function addSunsetHeaderToResponses(OA\Operation $operation, CarbonImmutable $sunsetDate): void
    {
        if ($operation->responses === Generator::UNDEFINED) {
            return;
        }

        $sunsetHeader = new Header(
            header: 'Sunset',
            description: 'This endpoint is deprecated and will be sunset on the example date. If an endpoint is over this date but still accessible, it can be removed any time.',
            schema: new Schema(
                type: 'string',
                format: 'http-date',
                example: $sunsetDate->toRfc7231String(),
            )
        );

        foreach ($operation->responses as $response) {
            if (! in_array((string) $response->response, ['200', '201', '204'], true)) {
                continue;
            }
            if ($response->headers === Generator::UNDEFINED) {
                $response->headers = [];
            }
            $response->headers[] = $sunsetHeader;
        }
    }

    private function makeSunsetDate(string $deprecationDate): CarbonImmutable
    {
        return Carbon::createFromFormat('Y-m-d', $deprecationDate)
            ->startOfDay()
            ->addMonths($this->config->deprecationFilter->monthsBeforeRemoval)
            ->toImmutable();
    }
}
