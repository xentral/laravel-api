<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

#[\Attribute]
class IdFilter extends FilterProperty
{
    public function __construct(
        public string $name = 'id',
        public ?string $type = 'integer',
        public array $operators = FilterOperator::ID,
    ) {
        parent::__construct(
            name: $name,
            type: $type,
            operators: $operators,
        );
    }
}
