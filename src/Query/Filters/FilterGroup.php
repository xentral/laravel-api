<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

/**
 * A parsed filter group: one boolean operator over an ordered condition set.
 *
 * The shape is already the recursive one nested groups will need; until then
 * the parser rejects a condition that is itself a group.
 */
final readonly class FilterGroup
{
    /** @param list<array{key: string, filter: array{operator: string, value: mixed}}> $conditions */
    public function __construct(
        public FilterGroupOperator $operator,
        public array $conditions,
    ) {}
}
