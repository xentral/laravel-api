<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

#[\Attribute]
class NumberFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'number',
        public array $operators = FilterOperator::COMPARABLE,
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
