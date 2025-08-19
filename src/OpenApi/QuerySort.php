<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

use OpenApi\Annotations\Parameter;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class QuerySort extends Parameter
{
    public function __construct(
        array $properties,
        string $default = '-created_at',
        ?string $parameter = null,
        ?string $description = null,
        ?bool $deprecated = null,
        ?bool $allowEmptyValue = null,
        ?array $examples = null,
        ?bool $allowReserved = null,
        ?array $spaceDelimited = null,
        ?array $pipeDelimited = null,
        // annotation
        ?array $x = null,
        ?array $attachables = null,
    ) {
        $enum = [];
        foreach ($properties as $property) {
            $enum[] = $property;
            $enum[] = '-'.$property;
        }

        $description ??= implode('', [
            'Sort via ',
            collect($enum)->join(', ', ' and '),
            '. Default sort is ',
            $default,
        ]);

        $schema = new Schema(type: 'array', items: new Items(enum: $enum));

        parent::__construct([
            'parameter' => $parameter ?? Generator::UNDEFINED,
            'name' => 'sort',
            'description' => $description,
            'in' => 'query',
            'required' => false,
            'deprecated' => $deprecated ?? Generator::UNDEFINED,
            'allowEmptyValue' => $allowEmptyValue ?? Generator::UNDEFINED,
            'ref' => Generator::UNDEFINED,
            'example' => Generator::UNDEFINED,
            'style' => 'form',
            'explode' => false,
            'allowReserved' => $allowReserved ?? Generator::UNDEFINED,
            'spaceDelimited' => $spaceDelimited ?? Generator::UNDEFINED,
            'pipeDelimited' => $pipeDelimited ?? Generator::UNDEFINED,
            'x' => $x ?? Generator::UNDEFINED,
            'value' => $this->combine($schema, $examples, $attachables),
        ]);
    }
}
