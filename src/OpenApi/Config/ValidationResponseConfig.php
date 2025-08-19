<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\Config;

readonly class ValidationResponseConfig
{
    public function __construct(
        public int $statusCode,
        public string $contentType,
        public int $maxErrors,
        public array $content,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            statusCode: $config['status_code'],
            contentType: $config['content_type'],
            maxErrors: $config['max_errors'],
            content: $config['content'],
        );
    }
}
