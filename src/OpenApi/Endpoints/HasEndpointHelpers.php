<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

use Illuminate\Support\Arr;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\RequestBody;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\Filters\FilterParameter;
use Xentral\LaravelApi\OpenApi\QuerySort;

trait HasEndpointHelpers
{
    protected function response(string $status, string $description, ?array $properties = null): Response
    {
        return new Response(response: $status, description: $description, content: $properties ? new JsonContent(properties: $properties) : null);
    }

    protected function resourceResponse(string $status, string $description, string $resource): Response
    {
        return $this->response($status, $description, [new Property('data', ref: $resource, x: ['description-ignore' => true, 'example-ignore' => true])]);
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

    protected function makeNegativeResponses(bool $with404 = false): array
    {
        return array_filter([
            $this->response401(),
            $this->response403(),
            $with404 ? $this->response404() : null,
        ]);
    }

    protected function makeParameters(array $parameters, string $path, array $filters = [], array $includes = []): array
    {
        $parameters = array_merge(
            $parameters,
            $this->createMissingPathParameters(
                $path,
                array_filter($parameters, fn ($p) => ! $p instanceof FilterParameter && ! $p instanceof QuerySort)),
        );
        if (! empty($filters)) {
            $parameters[] = new Parameter(
                name: 'filter',
                in: 'query',
                required: false,
                schema: new Schema(
                    properties: $filters,
                    type: 'object',
                ),
                style: 'deepObject',
            );
        }
        if (! empty($includes)) {
            $parameters[] = new Parameter(
                name: 'include',
                in: 'query',
                required: false,
                schema: new Schema(
                    type: 'array',
                    items: new Items(type: 'string', enum: $includes),
                ),
                explode: false,
            );
        }

        return $parameters;
    }

    protected function createMissingPathParameters(string $path, array $parameters): array
    {
        preg_match_all('/{([^}]+)}/', $path, $matches);
        $missing = [];
        foreach ($matches[1] as $match) {
            $hasParam = count(array_filter($parameters, fn (Parameter $parameter) => $parameter->name === $match)) > 0;
            if ($hasParam) {
                continue;
            }
            $missing[] = new Parameter(
                name: $match,
                in: 'path',
                required: true,
                schema: new Schema(type: 'string')
            );
        }

        return $missing;
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
        string|array|null $request = null,
        ?array $problems = null,
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
        if ($problems) {
            $x['problems'] = array_map(
                fn ($problem) => $problem instanceof \BackedEnum ? $problem->value : $problem,
                $problems
            );
        }

        return empty($x) ? Generator::UNDEFINED : $x;
    }
}
