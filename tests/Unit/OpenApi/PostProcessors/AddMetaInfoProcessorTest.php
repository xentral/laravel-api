<?php declare(strict_types=1);

use OpenApi\Analysis;
use OpenApi\Attributes\Server;
use OpenApi\Context;
use Xentral\LaravelApi\OpenApi\PostProcessors\AddMetaInfoProcessor;
use Xentral\LaravelApi\OpenApi\SchemaInfo;

it('handles server variables correctly', function () {
    $schemaInfo = SchemaInfo::fromArray([
        'name' => 'Test API',
        'version' => '1.0.0',
        'description' => 'Test API description',
        'contact' => ['name' => 'Test', 'url' => 'https://test.com', 'email' => 'test@test.com'],
        'servers' => [
            [
                'url' => 'https://{xentralId}.xentral.biz',
                'description' => 'Your xentral instance',
                'variables' => [['xentralId' => ['default' => 'my-company']]],
            ],
        ],
    ]);

    $processor = new AddMetaInfoProcessor($schemaInfo);
    $analysis = new Analysis([], new Context);

    $processor($analysis);

    $server = $analysis->openapi->servers[0];
    expect($server)->toBeInstanceOf(Server::class);
    expect($server->variables)->toHaveCount(1);
    $variable = $server->variables[0];
    expect($variable->serverVariable)->toBe('xentralId');
    expect($variable->default)->toBe('my-company');
});
