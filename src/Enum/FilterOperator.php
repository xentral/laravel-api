<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Enum;

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
}
