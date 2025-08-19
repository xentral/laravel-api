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
    ) {
        $filters = collect($filters)
            ->flatMap(fn (mixed $f) => $f instanceof FilterSpecCollection ? $f->getFilterSpecification() : Arr::wrap($f))
            ->flatten(1);

        $key = $withCustom
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

        $schema = new Schema(
            type: 'array',
            items: new Items(
                properties: [
                    $key,
                    new Property(
                        property: 'op',
                        description: 'operator',
                        type: 'string',
                        enum: $filters->pluck('operators')->flatten()->unique()->all(),
                    ),
                    new Property(
                        property: 'value',
                        description: 'value oder so',
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
                ],
                type: 'object',
                additionalProperties: false,
            ),
        );

        parent::__construct([
            'parameter' => Generator::UNDEFINED,
            'name' => 'filter',
            'description' => "The filter parameter is used to filter the results of the given endpoint. \n\n\n**Supported filter operators by key:** \n\n".$filterAvailableOperatorDescription,
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
