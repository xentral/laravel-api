<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

use OpenApi\Annotations\Parameter;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
class SearchParameter extends Parameter
{
    public function __construct(
        ?string $description = null,
        ?bool $deprecated = null,
        ?array $x = null,
    ) {
        parent::__construct([
            'parameter' => Generator::UNDEFINED,
            'name' => 'search',
            'in' => 'query',
            'required' => false,
            'deprecated' => $deprecated ?? Generator::UNDEFINED,
            'description' => $description ?? 'Full-text-style search across predefined columns.',
            'schema' => new Schema(type: 'string'),
            'x' => $x ?? Generator::UNDEFINED,
        ]);
    }
}
