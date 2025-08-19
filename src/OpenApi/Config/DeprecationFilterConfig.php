<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\Config;

readonly class DeprecationFilterConfig
{
    public function __construct(
        public bool $enabled,
        public int $monthsBeforeRemoval,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            enabled: $config['enabled'],
            monthsBeforeRemoval: $config['months_before_removal'],
        );
    }
}
