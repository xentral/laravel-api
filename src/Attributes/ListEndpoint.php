<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use OpenApi\Annotations\Get;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\Enum\PaginationType;

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
        PaginationType $paginationType = PaginationType::SIMPLE,
        bool $isInternal = false,
        ?\DateTimeInterface $deprecated = null,
        \BackedEnum|string|null $featureFlag = null,
        string|array|null $scopes = null,
    ) {
        $responses = [
            $this->response('200', $description, [
                new Property('data', type: 'array', items: new Items(ref: $resource)),
                ...$this->getPaginationProperties($paginationType),
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
            'x' => $this->compileX($isInternal, $deprecated, $featureFlag, $scopes),
            'value' => $this->combine($responses, $parameters),
        ]);
    }

    private function createPaginationParameters(int $defaultPageSize, int $maxPageSize, PaginationType $type): array
    {
        $params = [
            new Parameter(
                name: 'per_page',
                description: sprintf('Number of items per page. Default: %d, Max: %d', $defaultPageSize, $maxPageSize),
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer', example: $defaultPageSize),
            ),
        ];
        if ($type === PaginationType::CURSOR) {
            $params[] = new Parameter(
                name: 'cursor',
                description: 'The cursor to use for the paginated call.',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string', example: 'eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0'),
            );
        }
        if ($type === PaginationType::SIMPLE || $type === PaginationType::TABLE) {
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

    private function getPaginationProperties(PaginationType $paginationType): array
    {
        if ($paginationType === PaginationType::SIMPLE) {
            return [
                new Property(
                    'meta',
                    properties: [
                        new Property(property: 'current_page', type: 'integer'),
                        new Property(property: 'from', type: 'integer', nullable: true),
                        new Property(property: 'path', type: 'string'),
                        new Property(property: 'per_page', type: 'integer'),
                        new Property(property: 'last_page', type: 'integer'),
                        new Property(property: 'to', type: 'integer', nullable: true),
                        new Property(property: 'total', type: 'integer'),
                        new Property(property: 'links', type: 'array', items: new Items(type: 'object')),
                    ],
                    type: 'object',
                ),
            ];
        }
        if ($paginationType === PaginationType::CURSOR) {
            return [
                new Property(
                    'links',
                    properties: [
                        new Property(property: 'first', type: 'string', nullable: true),
                        new Property(property: 'last', type: 'string', nullable: true),
                        new Property(property: 'prev', type: 'string', nullable: true),
                        new Property(property: 'next', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
                new Property(
                    'meta',
                    properties: [
                        new Property(property: 'path', type: 'string'),
                        new Property(property: 'per_page', type: 'integer'),
                        new Property(property: 'next_cursor', type: 'string', nullable: true),
                        new Property(property: 'prev_cursor', type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ];
        }

        return [
            new Property(
                'links',
                properties: [
                    new Property(property: 'first', type: 'string', nullable: true),
                    new Property(property: 'last', type: 'string', nullable: true),
                    new Property(property: 'prev', type: 'string', nullable: true),
                    new Property(property: 'next', type: 'string', nullable: true),
                ],
                type: 'object',
            ),
            new Property(
                'meta',
                properties: [
                    new Property(property: 'current_page', type: 'integer'),
                    new Property(property: 'from', type: 'integer', nullable: true),
                    new Property(property: 'last_page', type: 'integer'),
                    new Property(property: 'links', type: 'array', items: new Items(properties: [
                        new Property(property: 'url', type: 'string', nullable: true),
                        new Property(property: 'label', type: 'string'),
                        new Property(property: 'active', type: 'boolean'),
                    ], type: 'object')),
                    new Property(property: 'path', type: 'string'),
                    new Property(property: 'per_page', type: 'integer'),
                    new Property(property: 'to', type: 'integer', nullable: true),
                    new Property(property: 'total', type: 'integer'),
                ],
                type: 'object',
            ),
        ];
    }
}
