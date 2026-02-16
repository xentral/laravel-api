<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Spatie\QueryBuilder\AllowedFilter;

class QueryFilter
{
    public function __construct(
        public string $name,
        public array $allowedOperators = [FilterOperator::EQUALS],
        public ?string $internalName = null,
    ) {}

    public static function identifier(string $name = 'id', ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter([
                FilterOperator::EQUALS,
                FilterOperator::NOT_EQUALS,
                FilterOperator::IN,
                FilterOperator::NOT_IN,
                FilterOperator::IS_NULL,
                FilterOperator::IS_NOT_NULL,
            ]),
            $internalName,
        );
    }

    public static function date(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new DateOperatorFilter,
            $internalName,
        );
    }

    public static function datetime(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new DateTimeOperatorFilter,
            $internalName,
        );
    }

    public static function string(string $name, ?string $internalName = null, ?string $enum = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter([
                FilterOperator::EQUALS,
                FilterOperator::NOT_EQUALS,
                FilterOperator::IN,
                FilterOperator::NOT_IN,
                FilterOperator::CONTAINS,
                FilterOperator::NOT_CONTAINS,
                FilterOperator::STARTS_WITH,
                FilterOperator::ENDS_WITH,
                FilterOperator::IS_NULL,
                FilterOperator::IS_NOT_NULL,
            ], $enum),
            $internalName,
        );
    }

    public static function number(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new NumberOperatorFilter($name),
            $internalName,
        );
    }

    public static function boolean(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter([
                FilterOperator::EQUALS,
                FilterOperator::NOT_EQUALS,
            ]),
            $internalName,
        );
    }

    public static function booleanInteger(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom($name, new BooleanIntegerFilter, $internalName);
    }

    public function make(
        string $name,
        array $allowedOperators = [],
        ?string $internalName = null,
        ?string $enum = null,
    ): AllowedFilter {
        $operators = empty($allowedOperators) ? FilterOperator::cases() : $allowedOperators;

        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter($operators, $enum),
            $internalName,
        );
    }
}
