<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Http;

use Illuminate\Support\Carbon;

trait HasSunsetHeader
{
    protected ?Carbon $deprecatedSince = null;

    public function deprecatedSince(\DateTimeInterface $date): self
    {
        $this->deprecatedSince = Carbon::instance($date);

        return $this;
    }

    public function toResponse($request)
    {
        /** @var \Illuminate\Http\JsonResponse $response */
        $response = parent::toResponse($request);

        if ($this->deprecatedSince) {
            $response->header('Sunset', $this->deprecatedSince->startOfDay()->toRfc7231String());
        }

        return $response;
    }
}
