<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

use OpenApi\Annotations\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\PaginationType;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class ListEndpoint extends Get
{
    use HasEndpointHelpers;

    public function __construct(
        string $path,
        string $resource,
        ?string $description = null,
        array $filters = [],
        array $includes = [],
        array $parameters = [],
        ?array $tags = null,
        ?array $security = null,
        ?string $summary = null,
        ?string $operationId = null,
        int $defaultPageSize = 15,
        int $maxPageSize = 100,
        PaginationType|array $paginationType = PaginationType::SIMPLE,
        bool $isInternal = false,
        ?\DateTimeInterface $deprecated = null,
        \BackedEnum|string|null $featureFlag = null,
        string|array|null $scopes = null,
        ?array $problems = null,
    ) {
        $responses = [
            $this->response('200', $description, [
                new Property('data', type: 'array', items: new Items(ref: $resource, x: ['description-ignore' => true, 'example-ignore' => true]), x: ['description-ignore' => true, 'example-ignore' => true]),
            ]),
            ...$this->makeNegativeResponses(),
        ];
        $parameters = $this->makeParameters($parameters, $path, $filters, $includes);

        parent::__construct([
            'path' => $path,
            'operationId' => $operationId ?? Generator::UNDEFINED,
            'description' => $description ?? Generator::UNDEFINED,
            'summary' => $summary ?? Generator::UNDEFINED,
            'security' => $security ?? Generator::UNDEFINED,
            'servers' => Generator::UNDEFINED,
            'tags' => $tags ?? Generator::UNDEFINED,
            'callbacks' => Generator::UNDEFINED,
            'deprecated' => $deprecated !== null ? true : Generator::UNDEFINED,
            'x' => $this->mergeX($this->compileX($isInternal, $deprecated, $featureFlag, $scopes, null, $problems), [
                'pagination_type' => $paginationType,
                'pagination_config' => [
                    'default_page_size' => $defaultPageSize,
                    'max_page_size' => $maxPageSize,
                ],
            ]),
            'value' => $this->combine($responses, $parameters),
        ]);
    }

    private function mergeX(string|array $baseX, array $additionalX): string|array
    {
        if ($baseX === Generator::UNDEFINED && empty($additionalX['pagination_type'])) {
            return Generator::UNDEFINED;
        }

        return array_merge($baseX === Generator::UNDEFINED ? [] : $baseX, $additionalX);
    }
}
