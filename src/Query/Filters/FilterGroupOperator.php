<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

/**
 * The boolean operator combining the conditions of a filter group.
 *
 * `and` is what the flat filter list already means, so it needs no endpoint
 * opt-in; `or` changes how a query is built and does.
 */
enum FilterGroupOperator: string
{
    case And = 'and';
    case Or = 'or';
}
