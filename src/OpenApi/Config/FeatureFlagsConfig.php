<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\Config;

readonly class FeatureFlagsConfig
{
    public function __construct(
        public string $descriptionPrefix,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            descriptionPrefix: $config['description_prefix'],
        );
    }
}
