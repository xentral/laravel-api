<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi;

readonly class SchemaDefinition
{
    public function __construct(
        public SchemaConfig $config,
        public SchemaInfo $info,
    ) {}

    public static function fromArray(array $schema): self
    {
        return new self(
            config: SchemaConfig::fromArray($schema['config']),
            info: SchemaInfo::fromArray($schema['info']),
        );
    }
}
