<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class ApiResource extends JsonResource
{
    use HasSunsetHeader;

    protected function wantsToInclude(string $include): bool
    {
        return app(QueryBuilderRequest::class)->includes()->contains($include);
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

    protected function reference(string $relation): ?array
    {
        $foreignKey = $this->resource->$relation()->getForeignKeyName();

        return $this->resource->$foreignKey ? ['id' => (string) $this->resource->$foreignKey] : null;
    }

    public static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
