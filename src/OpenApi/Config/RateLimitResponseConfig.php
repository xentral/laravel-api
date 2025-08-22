<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\Config;

readonly class RateLimitResponseConfig
{
    public function __construct(
        public bool $enabled,
        public string $message,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            enabled: $config['enabled'] ?? true,
            message: $config['message'] ?? 'Too Many Requests',
        );
    }
}
