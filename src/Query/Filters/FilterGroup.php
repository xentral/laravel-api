<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

/**
 * A parsed filter group: one boolean operator over an ordered condition set.
 *
 * A condition is either a filter triple or a sub-group, which is what makes
 * the shape recursive; QueryBuilderRequest caps how deep it may go.
 */
final readonly class FilterGroup
{
    /** @param list<array{key: string, filter: array{operator: string, value: mixed}}|self> $conditions */
    public function __construct(
        public FilterGroupOperator $operator,
        public array $conditions,
    ) {}

    public function hasSubgroups(): bool
    {
        foreach ($this->conditions as $condition) {
            if ($condition instanceof self) {
                return true;
            }
        }

        return false;
    }
}
