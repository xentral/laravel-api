<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

enum FilterOperator: string
{
    case EQUALS = 'equals';
    case NOT_EQUALS = 'notEquals';
    case IN = 'in';
    case NOT_IN = 'notIn';
    case CONTAINS = 'contains';
    case NOT_CONTAINS = 'notContains';
    case STARTS_WITH = 'startsWith';
    case ENDS_WITH = 'endsWith';
    case GREATER_THAN = 'greaterThan';
    case GREATER_THAN_OR_EQUALS = 'greaterThanOrEquals';
    case LESS_THAN = 'lessThan';
    case LESS_THAN_OR_EQUALS = 'lessThanOrEquals';
    case IS_NULL = 'isNull';
    case IS_NOT_NULL = 'isNotNull';

    /**
     * The operator sets below are the single source of truth per filter
     * family. Both layers reference them — the OpenApi filter attributes
     * (documented operators) and the QueryFilter helpers / operator filters
     * (accepted operators) — so the documentation cannot drift from the
     * runtime again.
     */
    public const ID = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::IN,
        self::NOT_IN,
        self::IS_NULL,
        self::IS_NOT_NULL,
    ];

    public const TEXT = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::IN,
        self::NOT_IN,
        self::CONTAINS,
        self::NOT_CONTAINS,
        self::STARTS_WITH,
        self::ENDS_WITH,
        self::IS_NULL,
        self::IS_NOT_NULL,
    ];

    public const COMPARABLE = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::LESS_THAN,
        self::LESS_THAN_OR_EQUALS,
        self::GREATER_THAN,
        self::GREATER_THAN_OR_EQUALS,
        self::IS_NULL,
        self::IS_NOT_NULL,
    ];

    /**
     * Pure membership tests — the set for keys whose values are opaque
     * identifiers or enum cases: IdCallbackFilter always, and IdFilter
     * call sites paired with QueryFilter::identifierCallback().
     */
    public const MEMBERSHIP = [
        self::EQUALS,
        self::NOT_EQUALS,
        self::IN,
        self::NOT_IN,
    ];

    public const ENUM = self::MEMBERSHIP;

    public const BOOLEAN = [
        self::EQUALS,
        self::NOT_EQUALS,
    ];

    /**
     * The positive counterpart of a negated operator, used to express a
     * negative filter on a relation as whereDoesntHave(positive form).
     * Returns null for operators that are not negations (isNotNull is
     * handled by its own null-semantics branch, never by inversion).
     */
    public function positiveForm(): ?self
    {
        return match ($this) {
            self::NOT_EQUALS => self::EQUALS,
            self::NOT_IN => self::IN,
            self::NOT_CONTAINS => self::CONTAINS,
            default => null,
        };
    }
}
