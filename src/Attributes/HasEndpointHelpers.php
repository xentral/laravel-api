<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use Illuminate\Support\Arr;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\AttributeFactory;

trait HasEndpointHelpers
{
    protected function response(string $status, string $description, ?array $properties = null): Response
    {
        return new Response(response: $status, description: $description, content: $properties ? new JsonContent(properties: $properties) : null);
    }

    protected function response204(): Response
    {
        return new Response(response: '204', description: 'No Content');
    }

    protected function response401(): Response
    {
        return new Response(response: '401', description: 'Unauthorized');
    }

    protected function response403(): Response
    {
        return new Response(response: '403', description: 'Unauthorized');
    }

    protected function response404(): Response
    {
        return new Response(response: '404', description: 'Not Found');
    }

    protected function makeNegativeResponses(?string $request = null, bool $with404 = false): array
    {
        return array_filter([
            $request ? AttributeFactory::createValidationResponse($request) : null,
            $this->response401(),
            $this->response403(),
            $with404 ? $this->response404() : null,
        ]);
    }

    protected function makeParameters(array $parameters, string $path, array $filters = [], array $includes = []): array
    {
        $parameters = array_merge($parameters, AttributeFactory::createMissingPathParameters($path, $parameters));
        if (! empty($filters)) {
            $parameters[] = AttributeFactory::createFilterParameter($filters);
        }
        if (! empty($includes)) {
            $parameters[] = AttributeFactory::createIncludeParameter($includes);
        }

        return $parameters;
    }

    protected function makeRequestBody(?string $request = null, string $contentType = 'application/json'): ?RequestBody
    {
        return $request
            ? new RequestBody(content: new MediaType($contentType, schema: new Schema(ref: $request)))
            : null;
    }

    protected function compileX(
        bool $isInternal,
        ?\DateTimeInterface $deprecated,
        \BackedEnum|string|null $featureFlag,
        string|array|null $scopes = null,
        ?string $request = null,
    ): string|array {
        $x = [];
        if ($isInternal) {
            $x['internal'] = true;
        }
        if ($deprecated) {
            $x['deprecated_on'] = $deprecated->format('Y-m-d');
        }
        if ($featureFlag) {
            if ($featureFlag instanceof \BackedEnum) {
                $featureFlag = $featureFlag->value;
            }
            $x['feature_flag'] = $featureFlag;
        }
        if ($scopes) {
            $x['scopes'] = Arr::wrap($scopes);
        }
        if ($request) {
            $x['request'] = $request;
        }

        return empty($x) ? Generator::UNDEFINED : $x;
    }
}
