<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Config;

readonly class PaginationResponseConfig
{
    public function __construct(
        public string $casing,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            casing: $config['casing'] === 'camel' ? 'camel' : 'snake',
        );
    }
}
