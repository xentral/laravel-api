<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

#[\Attribute]
class DateTimeFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'date-time',
        public array $operators = [
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::LESS_THAN,
            FilterOperator::LESS_THAN_OR_EQUALS,
            FilterOperator::GREATER_THAN,
            FilterOperator::GREATER_THAN_OR_EQUALS,
            FilterOperator::IS_NULL,
            FilterOperator::IS_NOT_NULL,
        ],
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
