<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use Xentral\LaravelApi\Enum\FilterOperator;

#[\Attribute]
class StringFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'string',
        public array $operators = [
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::IN,
            FilterOperator::NOT_IN,
            FilterOperator::CONTAINS,
            FilterOperator::NOT_CONTAINS,
            FilterOperator::STARTS_WITH,
            FilterOperator::ENDS_WITH,
        ],
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
