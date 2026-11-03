<?php declare(strict_types=1);

use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\Endpoints\GetEndpoint;
use Xentral\LaravelApi\OpenApi\Responses\PdfMediaType;

it('includes standard responses without additionalMediaTypes', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user'
    );

    $responses = $endpoint->responses;
    $responseCodes = array_map(fn ($response) => $response->response, $responses);

    expect($responseCodes)->toBe(['200', '401', '403', '404']);
});

it('includes additional MediaType in 200 response', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user',
        additionalMediaTypes: [
            new MediaType(
                mediaType: 'application/pdf',
                schema: new Schema(type: 'string', format: 'binary')
            ),
        ],
    );

    $responses = $endpoint->responses;
    $successResponse = $responses[0];

    expect($successResponse->content)->toBeArray()->toHaveCount(2);

    $contentTypes = array_map(fn ($content) => $content->mediaType, $successResponse->content);
    expect($contentTypes)->toBe(['application/json', 'application/pdf']);
});

it('works with PdfMediaType convenience class', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user',
        additionalMediaTypes: [new PdfMediaType],
    );

    $responses = $endpoint->responses;
    $successResponse = $responses[0];

    expect($successResponse->content)->toBeArray()->toHaveCount(2);

    $contentTypes = array_map(fn ($content) => $content->mediaType, $successResponse->content);
    expect($contentTypes)->toBe(['application/json', 'application/pdf']);
});

it('supports multiple additional media types', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user',
        additionalMediaTypes: [
            new PdfMediaType,
            new MediaType(
                mediaType: 'text/csv',
                schema: new Schema(type: 'string')
            ),
        ],
    );

    $responses = $endpoint->responses;
    $successResponse = $responses[0];

    expect($successResponse->content)->toBeArray()->toHaveCount(3);

    $contentTypes = array_map(fn ($content) => $content->mediaType, $successResponse->content);
    expect($contentTypes)->toBe(['application/json', 'application/pdf', 'text/csv']);
});

it('renders a scoped include into the enum, the description and x-include-scopes', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user',
        includes: ['project', 'salesPrices' => 'salesPrice:read', 'files' => ['files:read', 'other:read']],
    );

    $include = includeParameter($endpoint);

    expect($include->schema->items->enum)->toBe(['project', 'salesPrices', 'files'])
        ->and($include->x)->toBe(['include-scopes' => ['salesPrices' => ['salesPrice:read'], 'files' => ['files:read', 'other:read']]])
        ->and($include->description)->toBe('`salesPrices` requires the scope `salesPrice:read`. `files` requires the scopes `files:read`, `other:read`.');
});

it('leaves the include parameter without description and extension when no include is scoped', function () {
    $endpoint = new GetEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Get user',
        includes: ['project', 'lineItems'],
    );

    $include = includeParameter($endpoint);

    expect($include->schema->items->enum)->toBe(['project', 'lineItems'])
        ->and($include->description)->toBe(Generator::UNDEFINED)
        ->and($include->x)->toBe(Generator::UNDEFINED);
});
