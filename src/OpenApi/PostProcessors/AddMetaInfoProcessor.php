<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Attributes\Components;
use OpenApi\Attributes\Contact;
use OpenApi\Attributes\Info;
use OpenApi\Attributes\OpenApi;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Attributes\SecurityScheme;
use OpenApi\Attributes\Server;

class AddMetaInfoProcessor
{
    public function __construct(private readonly array $config) {}

    public function __invoke(Analysis $analysis)
    {
        $analysis->openapi = new OpenApi(
            info: new Info(
                version: $this->config['version'] ?? '1.0.0',
                description: $this->config['description'] ?? '',
                title: $this->config['name'],
                contact: new Contact(
                    name: $this->config['contact']['name'],
                    url: $this->config['contact']['url'],
                    email: $this->config['contact']['email'],
                ),
            ),
            servers: array_map(
                fn ($server) => new Server(...$server),
                $this->config['servers'],
            ),
            security: [['BearerAuth' => []]],
            components: new Components(
                schemas: [
                    ($this->config['exclude_money'] ?? false) ? null : new Schema(
                        schema: 'Money',
                        properties: [
                            new Property(property: 'amount', oneOf: [new Schema(type: 'string'), new Schema(type: 'number')]),
                            new Property(property: 'currency', type: 'string'),
                        ],
                        type: 'object',
                        additionalProperties: false,
                    ),
                ],
                securitySchemes: [
                    new SecurityScheme(
                        securityScheme: 'BearerAuth',
                        type: 'http',
                        scheme: 'bearer',
                    ),
                ]
            )
        );
    }
}
