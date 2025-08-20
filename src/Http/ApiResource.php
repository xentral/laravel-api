<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

abstract class ApiResource extends JsonResource
{
    protected ?Carbon $deprecatedSince = null;

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

    public function deprecatedSince(\DateTimeInterface $date): self
    {
        $this->deprecatedSince = Carbon::instance($date);

        return $this;
    }

    public function toResponse($request)
    {
        $response = (new ResourceResponse($this))->toResponse($request);

        if ($this->deprecatedSince) {
            $response->header('Sunset', $this->deprecatedSince->startOfDay()->toRfc7231String());
        }

        return $response;
    }

    public static function newCollection($resource): ApiResourceCollection
    {
        return new ApiResourceCollection($resource, static::class);
    }
}
