<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Illuminate\Support\Arr;
use OpenApi\Annotations\Parameter;
use OpenApi\Attributes\Attachable;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\XmlContent;
use OpenApi\Generator;
use Xentral\LaravelApi\Http\QueryBuilderRequest;
use Xentral\LaravelApi\OpenApi\PostProcessors\FilterGroupComponentsProcessor;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class FilterParameter extends Parameter
{
    public function __construct(
        array $filters = [],
        ?bool $deprecated = null,
        ?bool $allowEmptyValue = null,
        string|object|null $ref = null,
        ?array $examples = null,
        array|JsonContent|XmlContent|Attachable|null $content = null,
        ?bool $allowReserved = null,
        // annotation
        ?array $x = null,
        ?array $attachables = null,
        bool $withCustom = false,
        bool $supportsOrGroups = false,
        ?string $groupSchemaName = null,
    ) {
        if ($groupSchemaName !== null && ! $supportsOrGroups) {
            throw new \InvalidArgumentException('groupSchemaName is only meaningful together with supportsOrGroups: true.');
        }

        $filters = collect($filters)
            ->flatMap(fn (mixed $f) => $f instanceof FilterSpecCollection ? $f->getFilterSpecification() : Arr::wrap($f))
            ->flatten(1);

        // swagger-php annotations are mutable objects the generator walks and
        // rewrites, so a condition schema that appears in two positions has to
        // be built twice - hence the factories rather than shared instances.
        $key = fn (): Property => $withCustom
            ? new Property(
                property: 'key',
                oneOf: [
                    new Schema(
                        title: 'String',
                        enum: $filters->pluck('name')->unique()->all(),
                    ),
                    new Schema(
                        title: 'custom',
                        type: 'string',
                    ),
                ]
            )
            : new Property(
                property: 'key',
                type: 'string',
                enum: $filters->pluck('name')->unique()->all(),
            );

        $filterAvailableOperatorDescription = $filters->map(fn (FilterProperty $filter) => sprintf(
            '`%s`: %s',
            $filter->name,
            collect($filter->operators)->map(fn ($op) => '*'.$op->value.'*')->implode(', ')
        ))->implode(" \n\n");

        $conditionProperties = fn (): array => [
            $key(),
            new Property(
                property: 'op',
                description: 'operator',
                type: 'string',
                // unique() keeps the original keys, so dropping duplicates
                // would leave gaps and serialise the enum as a map.
                enum: $filters->pluck('operators')->flatten()->unique()->values()->all(),
            ),
            new Property(
                property: 'value',
                description: 'The property value.',
                oneOf: [
                    new Schema(
                        title: 'String',
                        type: 'string',
                    ),
                    new Schema(
                        title: 'Array',
                        type: 'array',
                        items: new Items(type: 'string'),
                    ),
                ]
            ),
        ];

        $condition = fn (): Items => new Items(
            properties: $conditionProperties(),
            type: 'object',
            additionalProperties: false,
        );

        // A group that may contain groups has to refer to itself, which an
        // inline schema cannot do - hence the named components an endpoint
        // opts into. Without a name the 0.19.0 output is kept unchanged.
        $group = fn (): Schema => $groupSchemaName !== null
            ? new Schema(ref: '#/components/schemas/'.$groupSchemaName.'Group')
            : new Schema(
                title: 'Filter group',
                required: ['op', 'conditions'],
                properties: [
                    new Property(
                        property: 'op',
                        description: 'Boolean operator combining the conditions.',
                        type: 'string',
                        enum: ['and', 'or'],
                    ),
                    new Property(
                        property: 'conditions',
                        type: 'array',
                        items: $condition(),
                    ),
                ],
                type: 'object',
                additionalProperties: false,
            );

        $schema = $supportsOrGroups
            ? new Schema(
                oneOf: [
                    new Schema(
                        title: 'Conditions',
                        type: 'array',
                        items: $condition(),
                    ),
                    $group(),
                ],
            )
            : new Schema(
                type: 'array',
                items: $condition(),
            );

        if ($groupSchemaName !== null) {
            FilterGroupComponentsProcessor::register($groupSchemaName, $conditionProperties());
        }

        $description = "The filter parameter is used to filter the results of the given endpoint. \n\n\n**Supported filter operators by key:** \n\n".$filterAvailableOperatorDescription;

        if ($supportsOrGroups) {
            $description .= "\n\nAlternatively the filter accepts a single group object `{op, conditions}`: `or` matches records satisfying at least one condition, `and` all of them. ";
            $description .= $groupSchemaName !== null
                ? sprintf('A condition may itself be such a group, nesting to at most %d levels.', QueryBuilderRequest::MAX_GROUP_DEPTH)
                : 'Nested groups are not supported.';
        }

        parent::__construct([
            'parameter' => Generator::UNDEFINED,
            'name' => 'filter',
            'description' => $description,
            'in' => 'query',
            'required' => false,
            'deprecated' => $deprecated ?? Generator::UNDEFINED,
            'allowEmptyValue' => $allowEmptyValue ?? Generator::UNDEFINED,
            'ref' => $ref ?? Generator::UNDEFINED,
            'example' => Generator::UNDEFINED,
            'style' => 'deepObject',
            'explode' => Generator::UNDEFINED,
            'allowReserved' => $allowReserved ?? Generator::UNDEFINED,
            'spaceDelimited' => Generator::UNDEFINED,
            'pipeDelimited' => Generator::UNDEFINED,
            'x' => $x ?? Generator::UNDEFINED,
            'value' => $this->combine($schema, $examples, $content, $attachables),
        ]);
    }
}
