<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class ApiResource extends JsonResource
{
    protected function wantsToInclude(string $include): bool
    {
        return app(QueryBuilderRequest::class)->includes()->contains($include);
    }

    protected function nullWhenEmpty(array|Collection $data, string $key): mixed
    {
        if (! isset($data['key'])) {
            return null;
        }

        return empty($data['key']) ? null : $data['key'];
    }

    public static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
