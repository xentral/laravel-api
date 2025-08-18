<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use Xentral\LaravelApi\Enum\FilterOperator;

#[\Attribute]
class BooleanFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'boolean',
        public array $operators = [
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
        ],
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
