<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
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
            new StringOperatorFilter(FilterOperator::ID),
            $internalName,
        );
    }

    /**
     * Filters by id where the target is a predicate rather than a column.
     *
     * `$apply` receives the query and a non-empty list of ints, and constrains the
     * query to rows matching any of those ids. Operators, validation and negation
     * are handled by the filter.
     *
     * @param  callable(Builder, non-empty-list<int>): mixed  $apply
     */
    public static function identifierCallback(string $name, callable $apply, IdFilterTarget $target): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new IdCallbackFilter(\Closure::fromCallable($apply), $target),
        );
    }

    public static function date(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new DateOperatorFilter($name),
            $internalName,
        );
    }

    public static function datetime(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new DateTimeOperatorFilter($name),
            $internalName,
        );
    }

    public static function string(string $name, ?string $internalName = null, ?string $enum = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter(FilterOperator::TEXT, $enum),
            $internalName,
        );
    }

    /**
     * Filter for an enum-backed key. Unlike string(enum:), the key accepts
     * only the four operators EnumFilter documents — in particular no
     * isNull, whose empty-string branch would silently match the zero case
     * on int-backed enum columns.
     *
     * $operators widens that set for a nullable enum column, mirroring the
     * operators argument the EnumFilter attribute already takes on the spec
     * side. Add isNull/isNotNull only for a string-backed column, where
     * "null or empty" is the intended reading — on an int-backed one it is
     * the zero case this helper exists to prevent.
     *
     * @param  array<int, FilterOperator>  $operators
     */
    public static function enum(
        string $name,
        string $enum,
        ?string $internalName = null,
        array $operators = FilterOperator::ENUM,
    ): AllowedFilter {
        return AllowedFilter::custom(
            $name,
            new StringOperatorFilter($operators, $enum),
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
            new StringOperatorFilter(FilterOperator::BOOLEAN),
            $internalName,
        );
    }

    public static function booleanInteger(string $name, ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom($name, new BooleanIntegerFilter($name), $internalName);
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
