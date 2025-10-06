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
use OpenApi\Attributes\ServerVariable;
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
            servers: array_map(fn ($server) => $this->createServer($server), $this->info->servers),
            security: [['BearerAuth' => []]],
            components: new Components(
                schemas: [
                    $this->excludeMoney ? null : new Schema(
                        schema: 'Money',
                        description: 'Money',
                        properties: [
                            new Property(property: 'amount', oneOf: [new Schema(type: 'string'), new Schema(type: 'number')]),
                            new Property(property: 'currency', type: 'string'),
                        ],
                        type: 'object',
                        example: ['amount' => '13.37', 'currency' => 'EUR'],
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

    private function createServer(array $server): Server
    {
        $url = $server['url'] ?? null;
        $description = $server['description'] ?? null;
        $variables = null;

        if (isset($server['variables'])) {
            $variables = $this->processServerVariables($server['variables']);
        }

        return new Server(
            url: $url,
            description: $description,
            variables: $variables
        );
    }

    private function processServerVariables(array $variables): array
    {
        $serverVariables = [];

        foreach ($variables as $variableGroup) {
            if (is_array($variableGroup)) {
                foreach ($variableGroup as $variableName => $variableConfig) {
                    $serverVariables[] = new ServerVariable(
                        serverVariable: $variableName,
                        description: $variableConfig['description'] ?? null,
                        default: $variableConfig['default'] ?? null,
                        enum: $variableConfig['enum'] ?? null
                    );
                }
            }
        }

        return $serverVariables;
    }
}
