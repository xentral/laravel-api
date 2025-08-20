<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

readonly class SchemaInfo
{
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
        public array $contact,
        public array $servers,
    ) {}

    public static function fromArray(array $info): self
    {
        return new self(
            name: $info['name'] ?? 'API reference',
            version: $info['version'] ?? '1.0.0',
            description: $info['description'] ?? 'brief description of the API',
            contact: $info['contact'] ?? ['name' => 'API Support', 'url' => 'https://api.com', 'email' => 'api@ex.com'],
            servers: $info['servers'] ?? [['url' => 'https://api.com']],
        );
    }
}
