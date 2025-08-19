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
            name: $info['name'],
            version: $info['version'],
            description: $info['description'],
            contact: $info['contact'],
            servers: $info['servers'],
        );
    }
}
