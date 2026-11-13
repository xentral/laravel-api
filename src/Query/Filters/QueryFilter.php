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

    /**
     * Filters an id column. isNull matches what ApiResource::reference() renders as null, which is
     * NULL, '' and a zero in either spelling, and isNotNull is its complement.
     *
     * @see IdentifierFilter
     */
    public static function identifier(string $name = 'id', ?string $internalName = null): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new IdentifierFilter($name),
            $internalName,
        );
    }

    /**
     * Filters an id key whose payload always renders the reference from the loaded relation.
     *
     * isNotNull matches the records whose `$relation` resolves and isNull every other one, a
     * stored id whose row is gone included. The membership operators match on the foreign key of
     * `$relation`, which has to be a BelongsTo relation of the filtered model.
     *
     * A key rendered through includeWhenLoaded() or reference() shows a dangling id in its payload
     * and belongs to identifier().
     *
     * @see ReferenceFilter
     */
    public static function reference(string $name, string $relation): AllowedFilter
    {
        return AllowedFilter::custom(
            $name,
            new ReferenceFilter($relation, $name),
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
            new IdCallbackFilter(\Closure::fromCallable($apply), $target, $name),
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
            new StringOperatorFilter(FilterOperator::TEXT, $enum, $name),
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
            new StringOperatorFilter($operators, $enum, $name),
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

    /**
     * A flag is matched the way the payload shows it, which casts NULL to false: comparing the
     * column with true or false would leave NULL out of both.
     */
    public static function boolean(string $name, ?string $internalName = null): AllowedFilter
    {
        return self::booleanInteger($name, $internalName);
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
            new StringOperatorFilter($operators, $enum, $name),
            $internalName,
        );
    }
}
