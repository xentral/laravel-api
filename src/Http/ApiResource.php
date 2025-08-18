<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Http;

use Illuminate\Http\Resources\Json\JsonResource;
use Xentral\LaravelApi\QueryBuilderRequest;

abstract class ApiResource extends JsonResource
{
    protected function wantsToInclude(string $include): bool
    {
        return app(QueryBuilderRequest::class)->includes()->contains($include);
    }
}
