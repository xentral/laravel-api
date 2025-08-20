<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\Items;
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

            // Find the 200 response to add pagination properties
            foreach ($operation->responses as $response) {
                if ($response->response === '200') {
                    $this->addPaginationPropertiesToResponse($response, $paginationType);
                    break;
                }
            }

            // Clean up the custom extension
            unset($operation->x['pagination_type']);
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
                properties: $properties,
                type: 'object'
            );
        }

        // Replace the schema with oneOf
        $schema->oneOf = $oneOfSchemas;
        $schema->properties = [];
        $schema->type = Generator::UNDEFINED;
    }

    private function getPaginationProperties(PaginationType $paginationType): array
    {
        if ($paginationType === PaginationType::SIMPLE) {
            return [
                new Property(
                    'meta',
                    properties: [
                        new Property(property: $this->convertCase('current_page'), type: 'integer'),
                        new Property(property: $this->convertCase('from'), type: 'integer', nullable: true),
                        new Property(property: $this->convertCase('path'), type: 'string'),
                        new Property(property: $this->convertCase('per_page'), type: 'integer'),
                        new Property(property: $this->convertCase('last_page'), type: 'integer'),
                        new Property(property: $this->convertCase('to'), type: 'integer', nullable: true),
                        new Property(property: $this->convertCase('total'), type: 'integer'),
                        new Property(property: $this->convertCase('links'), type: 'array', items: new Items(type: 'object')),
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
                        new Property(property: $this->convertCase('first'), type: 'string', nullable: true),
                        new Property(property: $this->convertCase('last'), type: 'string', nullable: true),
                        new Property(property: $this->convertCase('prev'), type: 'string', nullable: true),
                        new Property(property: $this->convertCase('next'), type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
                new Property(
                    'meta',
                    properties: [
                        new Property(property: $this->convertCase('path'), type: 'string'),
                        new Property(property: $this->convertCase('per_page'), type: 'integer'),
                        new Property(property: $this->convertCase('next_cursor'), type: 'string', nullable: true),
                        new Property(property: $this->convertCase('prev_cursor'), type: 'string', nullable: true),
                    ],
                    type: 'object',
                ),
            ];
        }

        // TABLE pagination (default)
        return [
            new Property(
                'links',
                properties: [
                    new Property(property: $this->convertCase('first'), type: 'string', nullable: true),
                    new Property(property: $this->convertCase('last'), type: 'string', nullable: true),
                    new Property(property: $this->convertCase('prev'), type: 'string', nullable: true),
                    new Property(property: $this->convertCase('next'), type: 'string', nullable: true),
                ],
                type: 'object',
            ),
            new Property(
                'meta',
                properties: [
                    new Property(property: $this->convertCase('current_page'), type: 'integer'),
                    new Property(property: $this->convertCase('from'), type: 'integer', nullable: true),
                    new Property(property: $this->convertCase('last_page'), type: 'integer'),
                    new Property(property: $this->convertCase('links'), type: 'array', items: new Items(properties: [
                        new Property(property: $this->convertCase('url'), type: 'string', nullable: true),
                        new Property(property: $this->convertCase('label'), type: 'string'),
                        new Property(property: $this->convertCase('active'), type: 'boolean'),
                    ], type: 'object')),
                    new Property(property: $this->convertCase('path'), type: 'string'),
                    new Property(property: $this->convertCase('per_page'), type: 'integer'),
                    new Property(property: $this->convertCase('to'), type: 'integer', nullable: true),
                    new Property(property: $this->convertCase('total'), type: 'integer'),
                ],
                type: 'object',
            ),
        ];
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
