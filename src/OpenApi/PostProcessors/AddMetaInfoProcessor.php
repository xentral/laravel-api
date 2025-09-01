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
use Xentral\LaravelApi\OpenApi\SchemaInfo;

class AddMetaInfoProcessor
{
    public function __construct(
        private readonly SchemaInfo $info,
        private readonly bool $excludeMoney = false
    ) {}

    public function __invoke(Analysis $analysis)
    {

        $analysis->openapi = new OpenApi(
            info: new Info(
                version: $this->info->version,
                description: $this->info->description,
                title: $this->info->name,
                contact: new Contact(
                    name: $this->info->contact['name'],
                    url: $this->info->contact['url'],
                    email: $this->info->contact['email'],
                ),
            ),
            servers: array_map(
                fn ($server) => new Server(...$server),
                $this->info->servers,
            ),
            security: [['BearerAuth' => []]],
            components: new Components(
                schemas: [
                    $this->excludeMoney ? null : new Schema(
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
