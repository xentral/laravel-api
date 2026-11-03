<?php declare(strict_types=1);

use OpenApi\Generator;
use Xentral\LaravelApi\OpenApi\Endpoints\ListEndpoint;

it('renders a scoped include into the enum, the description and x-include-scopes', function () {
    $endpoint = new ListEndpoint(
        path: '/users',
        resource: '#/components/schemas/User',
        description: 'List users',
        includes: ['salesPrices' => 'salesPrice:read', 'project'],
    );

    $include = includeParameter($endpoint);

    expect($include->schema->items->enum)->toBe(['salesPrices', 'project'])
        ->and($include->x)->toBe(['include-scopes' => ['salesPrices' => ['salesPrice:read']]])
        ->and($include->description)->toBe('`salesPrices` requires the scope `salesPrice:read`.');
});

it('leaves the include parameter without description and extension when no include is scoped', function () {
    $endpoint = new ListEndpoint(
        path: '/users',
        resource: '#/components/schemas/User',
        description: 'List users',
        includes: ['project'],
    );

    $include = includeParameter($endpoint);

    expect($include->schema->items->enum)->toBe(['project'])
        ->and($include->description)->toBe(Generator::UNDEFINED)
        ->and($include->x)->toBe(Generator::UNDEFINED);
});
