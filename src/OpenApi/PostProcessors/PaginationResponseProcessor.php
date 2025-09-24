<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\PaginationType;
use Xentral\LaravelApi\OpenApi\SchemaConfig;

class PaginationResponseProcessor
{
    public function __construct(
        private readonly SchemaConfig $config
    ) {}

    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (! isset($operation->x['pagination_type'])) {
                continue;
            }

            $paginationType = $operation->x['pagination_type'];
            $paginationConfig = $operation->x['pagination_config'] ?? [];

            // Add pagination parameters
            $this->addPaginationParameters($operation, $paginationType, $paginationConfig);

            // Find the 200 response to add pagination properties
            foreach ($operation->responses as $response) {
                if ($response->response === '200') {
                    $this->addPaginationPropertiesToResponse($response, $paginationType);
                    break;
                }
            }

            // Clean up the custom extensions
            unset($operation->x['pagination_type'], $operation->x['pagination_config']);
        }
    }

    private function addPaginationPropertiesToResponse(OA\Response $response, PaginationType|array $paginationType): void
    {
        if (! $response->content) {
            return;
        }

        $types = is_array($paginationType) ? $paginationType : [$paginationType];

        // Find the JSON media type
        foreach ($response->content as $mediaType) {
            if ($mediaType->mediaType === 'application/json') {
                if ($mediaType->schema && $mediaType->schema->properties) {
                    if (count($types) === 1) {
                        // Single pagination type - add properties directly
                        $paginationProperties = $this->getPaginationProperties($types[0]);
                        $mediaType->schema->properties = array_merge($mediaType->schema->properties, $paginationProperties);
                    } else {
                        // Multiple pagination types - create oneOf schema
                        $this->addOneOfPaginationSchema($mediaType->schema, $types);
                    }
                }
                break;
            }
        }
    }

    private function addOneOfPaginationSchema(OA\Schema $schema, array $types): void
    {
        $dataProperty = null;

        // Find and remove the existing data property
        foreach ($schema->properties as $index => $property) {
            if ($property->property === 'data') {
                $dataProperty = $property;
                unset($schema->properties[$index]);
                break;
            }
        }

        // Create oneOf schemas for each pagination type
        $oneOfSchemas = [];
        foreach ($types as $type) {
            $paginationProperties = $this->getPaginationProperties($type);
            $properties = [];

            // Add data property back
            if ($dataProperty) {
                $properties[] = $dataProperty;
            }

            // Add pagination properties
            $properties = array_merge($properties, $paginationProperties);

            $oneOfSchemas[] = new Schema(
                title: 'Paginated Response: '.$type->value,
                properties: $properties,
                type: 'object'
            );
        }

        // Replace the schema with oneOf
        $schema->oneOf = $oneOfSchemas;
        $schema->properties = Generator::UNDEFINED;
        $schema->type = Generator::UNDEFINED;
    }

    private function getPaginationProperties(PaginationType $paginationType): array
    {
        if ($paginationType === PaginationType::SIMPLE) {
            return [
                new Property(
                    'meta',
                    description: 'Pagination meta data',
                    properties: [
                        new Property(property: $this->convertCase('current_page'), description: 'The current page number', type: 'integer', example: 1),
                        new Property(property: $this->convertCase('from'), description: 'The first item being returned in the current page', type: 'integer', example: 1, nullable: true),
                        new Property(property: $this->convertCase('path'), description: 'The base path for the paginated results', type: 'string', example: 'https://api.example.com/items'),
                        new Property(property: $this->convertCase('per_page'), description: 'The number of items shown per page', type: 'integer', example: 15),
                        new Property(property: $this->convertCase('last_page'), description: 'The last page number', type: 'integer', example: 10),
                        new Property(property: $this->convertCase('to'), description: 'The last item being returned in the current page', type: 'integer', example: 15, nullable: true),
                        new Property(property: $this->convertCase('total'), description: 'The total number of items', type: 'integer', example: 150),
                        new Property(property: $this->convertCase('links'), description: 'Pagination links array', type: 'array', items: new Items(type: 'object')),
                    ],
                    type: 'object',
                    x: ['example-ignore' => true],
                ),
            ];
        }

        if ($paginationType === PaginationType::CURSOR) {
            return [
                new Property(
                    'links',
                    description: 'Pagination links',
                    properties: [
                        new Property(property: $this->convertCase('first'), description: 'URL to the first page', type: 'string', example: 'https://api.example.com/items?cursor=eyJpZCI6MSwiX3BvaW50c1RvTmV4dEl0ZW1zIjp0cnVlfQ', nullable: true),
                        new Property(property: $this->convertCase('last'), description: 'URL to the last page', type: 'string', example: null, nullable: true),
                        new Property(property: $this->convertCase('prev'), description: 'URL to the previous page', type: 'string', example: null, nullable: true),
                        new Property(property: $this->convertCase('next'), description: 'URL to the next page', type: 'string', example: 'https://api.example.com/items?cursor=eyJpZCI6MTYsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0', nullable: true),
                    ],
                    type: 'object',
                    x: ['example-ignore' => true],
                ),
                new Property(
                    'meta',
                    description: 'Pagination meta data',
                    properties: [
                        new Property(property: $this->convertCase('path'), description: 'The base path for the paginated results', type: 'string', example: 'https://api.example.com/items'),
                        new Property(property: $this->convertCase('per_page'), description: 'The number of items shown per page', type: 'integer', example: 15),
                        new Property(property: $this->convertCase('next_cursor'), description: 'Cursor for the next page', type: 'string', example: 'eyJpZCI6MTYsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0', nullable: true),
                        new Property(property: $this->convertCase('prev_cursor'), description: 'Cursor for the previous page', type: 'string', example: null, nullable: true),
                    ],
                    type: 'object',
                    x: ['example-ignore' => true],
                ),
            ];
        }

        // TABLE pagination (default)
        return [
            new Property(
                'links',
                properties: [
                    new Property(property: $this->convertCase('first'), description: 'URL to the first page', type: 'string', example: 'https://api.example.com/items?page=1', nullable: true),
                    new Property(property: $this->convertCase('last'), description: 'URL to the last page', type: 'string', example: 'https://api.example.com/items?page=10', nullable: true),
                    new Property(property: $this->convertCase('prev'), description: 'URL to the previous page', type: 'string', example: null, nullable: true),
                    new Property(property: $this->convertCase('next'), description: 'URL to the next page', type: 'string', example: 'https://api.example.com/items?page=2', nullable: true),
                ],
                type: 'object',
                x: ['description-ignore' => true, 'example-ignore' => true],
            ),
            new Property(
                'meta',
                properties: [
                    new Property(property: $this->convertCase('current_page'), description: 'The current page number', type: 'integer', example: 1),
                    new Property(property: $this->convertCase('from'), description: 'The first item being returned in the current page', type: 'integer', nullable: true, example: 1),
                    new Property(property: $this->convertCase('last_page'), description: 'The last page number', type: 'integer', example: 10),
                    new Property(property: $this->convertCase('links'), type: 'array', items: new Items(properties: [
                        new Property(property: $this->convertCase('url'), description: 'URL for the pagination link', type: 'string', nullable: true, example: 'https://api.example.com/items?page=2'),
                        new Property(property: $this->convertCase('label'), description: 'Label for the pagination link', type: 'string', example: '2'),
                        new Property(property: $this->convertCase('active'), description: 'Whether this link represents the current page', type: 'boolean', example: false),
                    ], type: 'object')),
                    new Property(property: $this->convertCase('path'), description: 'The base path for the paginated results', type: 'string', example: 'https://api.example.com/items'),
                    new Property(property: $this->convertCase('per_page'), description: 'The number of items shown per page', type: 'integer', example: 15),
                    new Property(property: $this->convertCase('to'), description: 'The last item being returned in the current page', type: 'integer', nullable: true, example: 15),
                    new Property(property: $this->convertCase('total'), description: 'The total number of items', type: 'integer', example: 150),
                ],
                type: 'object',
                x: ['description-ignore' => true, 'example-ignore' => true],
            ),
        ];
    }

    private function addPaginationParameters(OA\Operation $operation, PaginationType|array $paginationType, array $paginationConfig): void
    {
        $types = is_array($paginationType) ? $paginationType : [$paginationType];
        $defaultPageSize = $paginationConfig['default_page_size'] ?? 15;
        $maxPageSize = $paginationConfig['max_page_size'] ?? 100;

        if (count($types) === 1) {
            // Single pagination type - add parameters directly
            $paginationParameters = $this->createPaginationParameters($types[0], $defaultPageSize, $maxPageSize);
            $parameters = $operation->parameters === Generator::UNDEFINED ? [] : $operation->parameters;
            $operation->parameters = array_merge($parameters, $paginationParameters);
        } else {
            // Multiple pagination types - create individual parameter sets but merge common ones
            $this->addMultiTypePaginationParameters($operation, $types, $defaultPageSize, $maxPageSize);
        }
    }

    private function addMultiTypePaginationParameters(OA\Operation $operation, array $types, int $defaultPageSize, int $maxPageSize): void
    {
        $hasCursor = in_array(PaginationType::CURSOR, $types, true);
        $hasPageBased = in_array(PaginationType::SIMPLE, $types, true) || in_array(PaginationType::TABLE, $types, true);

        // Add x-pagination header parameter to control pagination type
        $typeValues = array_map(fn (PaginationType $type) => $type->value, $types);
        $typesList = implode(', ', $typeValues);

        $params = [
            new Parameter(
                name: 'x-pagination',
                description: "Controls the pagination format. Available types: {$typesList}. Defaults to 'simple' if not specified.",
                in: 'header',
                required: false,
                schema: new Schema(
                    type: 'string',
                    enum: $typeValues,
                    example: 'simple'
                ),
            ),
            new Parameter(
                name: $this->convertCase('per_page'),
                description: sprintf('Number of items per page. Default: %d, Max: %d', $defaultPageSize, $maxPageSize),
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer', example: $defaultPageSize),
            ),
        ];

        if ($hasPageBased) {
            $params[] = new Parameter(
                name: 'page',
                description: 'Page number. Only used with simple and table pagination.',
                in: 'query',
                required: false,
                schema: new Schema(type: 'integer', example: 1),
            );
        }
        if ($hasCursor) {
            $params[] = new Parameter(
                name: 'cursor',
                description: 'The cursor to use for the paginated call. Only used with cursor pagination. Not compatible with page parameter',
                in: 'query',
                required: false,
                schema: new Schema(type: 'string', example: 'eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0'),
            );
        }

        $parameters = $operation->parameters === Generator::UNDEFINED ? [] : $operation->parameters;
        $operation->parameters = array_merge($parameters, $params);
    }

    private function createPaginationParameters(PaginationType $type, int $defaultPageSize, int $maxPageSize): array
    {
        $params = [
            new Parameter(
                name: $this->convertCase('per_page'),
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
        } else {
            // Simple and Table both use page parameter
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

    private function convertCase(string $property): string
    {
        return $this->config->paginationResponse->casing === 'camel'
            ? $this->toCamelCase($property)
            : $property;
    }

    private function toCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
}
