<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Xentral\LaravelApi\Query\Filters\IdentifierFilter;

abstract class ApiResource extends JsonResource
{
    use HasSunsetHeader;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->initializeTraits();
    }

    protected function wantsToInclude(string $include): bool
    {
        return resolve(QueryBuilderRequest::class)->includes()->contains($include);
    }

    protected function nullWhenEmpty(array|Collection $data, string $key): mixed
    {
        if (! isset($data[$key])) {
            return null;
        }

        return empty($data[$key]) ? null : $data[$key];
    }

    protected function includeWhenLoaded(string $relation, string $resourceClass): mixed
    {
        return $this->whenLoaded(
            $relation,
            fn () => new $resourceClass($this->resource->$relation),
            fn () => $this->reference($relation),
        );
    }

    /**
     * Renders the foreign key of a relation that is not loaded. A key PHP reads as false, which is
     * NULL, '', '0' and 0, is no reference. IdentifierFilter::UNSET_VALUES is the same set on the
     * filter side, so change the two together.
     *
     * @see IdentifierFilter
     *
     * @return array{id: string}|null
     */
    protected function reference(string $relation): ?array
    {
        $foreignKey = $this->resource->$relation()->getForeignKeyName();

        return $this->resource->$foreignKey ? ['id' => (string) $this->resource->$foreignKey] : null;
    }

    public static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }

    protected function initializeTraits()
    {
        $class = static::class;
        foreach (class_uses_recursive($class) as $trait) {
            $method = 'initialize'.class_basename($trait);
            if (method_exists($this, $method)) {
                call_user_func([$this, $method]);
            }
        }
    }
}
