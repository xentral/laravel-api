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
 */
class IdCallbackFilter implements Filter
{
    use HasRepeatedFilterKeys;

    private const SUPPORTED_OPERATORS = [
        FilterOperator::EQUALS,
        FilterOperator::NOT_EQUALS,
        FilterOperator::IN,
        FilterOperator::NOT_IN,
    ];

    /**
     * @param  Closure(Builder, non-empty-list<int>): void  $apply
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
                ($this->apply)($query, [$id]);
            }

            return;
        }

        ($this->apply)($query, $ids);
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
}
