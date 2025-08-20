<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

use OpenApi\Annotations\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
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
    ) {
        $responses = [
            $this->response('200', $description, [
                new Property('data', type: 'array', items: new Items(ref: $resource)),
            ]),
            ...$this->makeNegativeResponses(),
        ];
        $parameters = $this->makeParameters($parameters, $path, $filters, $includes);
        $parameters = array_merge($parameters, $this->createPaginationParameters($defaultPageSize, $maxPageSize, $paginationType));

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
            'x' => $this->mergeX($this->compileX($isInternal, $deprecated, $featureFlag, $scopes), [
                'pagination_type' => $paginationType,
            ]),
            'value' => $this->combine($responses, $parameters),
        ]);
    }

    private function createPaginationParameters(int $defaultPageSize, int $maxPageSize, PaginationType|array $type): array
    {
        $types = is_array($type) ? $type : [$type];

        $params = [
            new Parameter(
                name: 'per_page',
                description: sprintf('Number of items per page. Default: %d, Max: %d', $defaultPageSize, $maxPageSize),
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer', example: $defaultPageSize),
            ),
        ];

        $hasCursor = in_array(PaginationType::CURSOR, $types, true);
        $hasPageBased = in_array(PaginationType::SIMPLE, $types, true) || in_array(PaginationType::TABLE, $types, true);

        if ($hasCursor) {
            $params[] = new Parameter(
                name: 'cursor',
                description: 'The cursor to use for the paginated call.',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string', example: 'eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0'),
            );
        }

        if ($hasPageBased) {
            $params[] = new Parameter(
                name: 'page',
                description: 'Page number.',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer', example: 1),
            );
        }

        return $params;
    }

    private function mergeX(string|array $baseX, array $additionalX): string|array
    {
        if ($baseX === Generator::UNDEFINED) {
            return $additionalX;
        }

        return array_merge($baseX, $additionalX);
    }
}
