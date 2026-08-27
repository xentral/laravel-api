<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

#[\Attribute]
class BooleanFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'boolean',
        public array $operators = FilterOperator::BOOLEAN,
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
