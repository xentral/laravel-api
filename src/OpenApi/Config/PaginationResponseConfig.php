<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Config;

readonly class PaginationResponseConfig
{
    public function __construct(
        public string $casing,
    ) {}

    public static function fromArray(array $config): self
    {
        $casing = $config['casing'] ?? 'snake';

        return new self(
            casing: $casing === 'camel' ? 'camel' : 'snake',
        );
    }
}
