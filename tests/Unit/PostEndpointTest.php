<?php declare(strict_types=1);

use Xentral\LaravelApi\OpenApi\Endpoints\PostEndpoint;

it('includes 404 response when path contains variables', function () {
    $endpoint = new PostEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Update user'
    );

    $responses = $endpoint->responses;
    $responseCodes = array_map(fn ($response) => $response->response, $responses);

    expect($responseCodes)->toContain('404');
});

it('does not include 404 response when path has no variables', function () {
    $endpoint = new PostEndpoint(
        path: '/users',
        resource: '#/components/schemas/User',
        description: 'Create user'
    );

    $responses = $endpoint->responses;
    $responseCodes = array_map(fn ($response) => $response->response, $responses);

    expect($responseCodes)->not->toContain('404');
});

it('includes 404 response when path contains multiple variables', function () {
    $endpoint = new PostEndpoint(
        path: '/users/{userId}/posts/{postId}',
        resource: '#/components/schemas/Post',
        description: 'Update post'
    );

    $responses = $endpoint->responses;
    $responseCodes = array_map(fn ($response) => $response->response, $responses);

    expect($responseCodes)->toContain('404');
});

it('always includes 401 and 403 responses regardless of path variables', function () {
    $endpointWithVariables = new PostEndpoint(
        path: '/users/{id}',
        resource: '#/components/schemas/User',
        description: 'Update user'
    );

    $endpointWithoutVariables = new PostEndpoint(
        path: '/users',
        resource: '#/components/schemas/User',
        description: 'Create user'
    );

    $responsesWithVariables = array_map(fn ($response) => $response->response, $endpointWithVariables->responses);
    $responsesWithoutVariables = array_map(fn ($response) => $response->response, $endpointWithoutVariables->responses);

    expect($responsesWithVariables)->toContain('401', '403');
    expect($responsesWithoutVariables)->toContain('401', '403');
});
