<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filters by id where the target is a predicate rather than a column: a relation,
 * a pivot, or a legacy column that encodes the id inside another value.
 *
 * The callback is handed a non-empty list of ids and constrains the query to rows
 * matching any of them. Operator handling, id validation and negation stay in this
 * filter, so a caller only writes the positive membership predicate.
 *
 * Each invocation of the callback is wrapped in its own nested where, so a predicate
 * using `orWhere` cannot widen the query it is applied to.
 */
class IdCallbackFilter implements Filter
{
    use HasRepeatedFilterKeys;

    private const SUPPORTED_OPERATORS = FilterOperator::MEMBERSHIP;

    /**
     * The return value of `$apply` is discarded, so a single expression arrow function
     * returning the builder is as valid as a block closure returning nothing.
     *
     * @param  Closure(Builder, non-empty-list<int>): mixed  $apply
     */
    public function __construct(
        private readonly Closure $apply,
        private readonly IdFilterTarget $target,
    ) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($this->applyRepeatedFilters($query, $value, $property)) {
            return;
        }

        $operator = $this->toOperator($value['operator'] ?? 'equals', $property);
        $ids = FilterValue::toIds($value['value'] ?? null, $property);

        if ($operator === FilterOperator::NOT_EQUALS || $operator === FilterOperator::NOT_IN) {
            $query->whereNot(function (Builder $query) use ($ids): void {
                ($this->apply)($query, $ids);
            });

            return;
        }

        if ($operator === FilterOperator::EQUALS && $this->target === IdFilterTarget::Relation && count($ids) > 1) {
            foreach ($ids as $id) {
                $this->applyGrouped($query, [$id]);
            }

            return;
        }

        $this->applyGrouped($query, $ids);
    }

    /**
     * @param  non-empty-list<int>  $ids
     */
    private function applyGrouped(Builder $query, array $ids): void
    {
        $query->where(function (Builder $query) use ($ids): void {
            ($this->apply)($query, $ids);
        });
    }

    private function toOperator(mixed $operator, string $property): FilterOperator
    {
        $parsed = is_string($operator) ? FilterOperator::tryFrom($operator) : null;

        if ($parsed === null || ! in_array($parsed, self::SUPPORTED_OPERATORS, true)) {
            throw ValidationException::withMessages([
                $property => "Unsupported operator: {$this->printableOperator($operator)}. Use 'equals', 'notEquals', 'in' or 'notIn'.",
            ]);
        }

        return $parsed;
    }

    private function printableOperator(mixed $operator): string
    {
        return is_scalar($operator) ? (string) $operator : gettype($operator);
    }

    public function allowedOperators(): array
    {
        return self::SUPPORTED_OPERATORS;
    }
}
