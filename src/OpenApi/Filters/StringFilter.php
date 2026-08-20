<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

#[\Attribute]
class StringFilter extends FilterProperty
{
    public function __construct(
        public string $name,
        public ?string $type = 'string',
        public array $operators = FilterOperator::TEXT,
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
