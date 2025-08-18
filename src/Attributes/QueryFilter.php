<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use OpenApi\Annotations\Parameter;
use OpenApi\Attributes\Attachable;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\XmlContent;
use OpenApi\Generator;
use Xentral\LaravelApi\Enum\FilterType;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class QueryFilter extends Parameter
{
    public function __construct(
        ?string $type = null,
        ?string $parameter = null,
        ?string $name = null,
        ?string $description = null,
        ?bool $deprecated = null,
        ?bool $allowEmptyValue = null,
        string|object|null $ref = null,
        mixed $example = '>=3',
        ?array $examples = null,
        array|JsonContent|XmlContent|Attachable|null $content = null,
        ?bool $allowReserved = null,
        ?array $spaceDelimited = null,
        ?array $pipeDelimited = null,
        // annotation
        ?array $x = null,
        ?array $attachables = null,
        bool $multiple = true,
        FilterType $filterType = FilterType::EXACT,
        array $operators = ['>=', '<=', '>', '<', '='],
        array|string|null $enum = null,
    ) {
        if (in_array($name, ['created_at', 'updated_at', 'deleted_at'])) {
            $example = '>=2025-03-01';
        }
        if ($type === 'operator') {
            $schema = new Schema(type: 'string', example: $example, enum: $enum);
            $description = $name.' Filter. available operators: '.implode(', ', $operators);
        } else {
            $schema = match ($multiple) {
                true => new Schema(type: 'array', items: new Items(type: $type, example: $example, enum: $enum)),
                default => new Schema(type: $type, example: $example, enum: $enum),
            };
            $name = $filterType === FilterType::EXACT ? $name : $name.'.'.$filterType->value;
            $description ??= $name.' Filter';
        }

        parent::__construct([
            'parameter' => $parameter ?? Generator::UNDEFINED,
            'name' => 'filter['.$name.']',
            'description' => $description,
            'in' => 'query',
            'required' => false,
            'deprecated' => $deprecated ?? Generator::UNDEFINED,
            'allowEmptyValue' => $allowEmptyValue ?? Generator::UNDEFINED,
            'ref' => $ref ?? Generator::UNDEFINED,
            'example' => Generator::UNDEFINED,
            'style' => 'form',
            'explode' => false,
            'allowReserved' => $allowReserved ?? Generator::UNDEFINED,
            'spaceDelimited' => $spaceDelimited ?? Generator::UNDEFINED,
            'pipeDelimited' => $pipeDelimited ?? Generator::UNDEFINED,
            'x' => $x ?? Generator::UNDEFINED,
            'value' => $this->combine($schema, $examples, $content, $attachables),
        ]);
    }
}
