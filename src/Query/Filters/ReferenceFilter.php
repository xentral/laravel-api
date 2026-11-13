<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * An id key whose payload always renders the reference from the loaded relation, so the field is
 * null wherever the related row cannot be loaded, including a stored id whose row is gone.
 *
 * isNotNull and isNull therefore answer for the relation: isNotNull is exactly the records whose
 * reference resolves, under the global scopes the eager loaded relation sees, and isNull is its
 * complement. equals, notEquals, in and notIn match on the stored foreign key, where the caller
 * already names the id it is looking for.
 *
 * A key whose payload falls back to ApiResource::reference() renders a dangling id and belongs to
 * IdentifierFilter instead.
 *
 * @see IdentifierFilter
 */
class ReferenceFilter implements Filter
{
    use HasRepeatedFilterKeys;

    private readonly IdentifierFilter $storedReference;

    public function __construct(
        private readonly string $relation,
        string $filterName,
    ) {
        $this->storedReference = new IdentifierFilter($filterName);
    }

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        if ($this->applyRepeatedFilters($query, $value, $property)) {
            return;
        }

        $foreignKey = $this->foreignKeyOn($query);

        match (is_array($value) ? $value['operator'] ?? null : null) {
            FilterOperator::IS_NULL->value => $query->whereDoesntHave($this->relation),
            FilterOperator::IS_NOT_NULL->value => $query->whereHas($this->relation),
            default => ($this->storedReference)($query, $value, $foreignKey),
        };
    }

    /**
     * @return list<FilterOperator>
     */
    public function allowedOperators(): array
    {
        return $this->storedReference->allowedOperators();
    }

    /**
     * The column is read off the relation, the way ApiResource::reference() reads it, so the filter
     * and the payload cannot name two different columns.
     */
    private function foreignKeyOn(Builder $query): string
    {
        $model = $query->getModel();
        $relation = $model->isRelation($this->relation) ? $model->{$this->relation}() : null;

        if (! $relation instanceof BelongsTo) {
            throw new \LogicException(sprintf(
                'A reference filter needs a BelongsTo relation, which %s::%s is not.',
                class_basename($model),
                $this->relation,
            ));
        }

        return $relation->getForeignKeyName();
    }
}
