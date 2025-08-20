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
            statusCode: $config['status_code'] ?? 422,
            contentType: $config['content_type'] ?? 'application/json',
            maxErrors: $config['max_errors'] ?? 3,
            content: $config['content'] ?? ['message' => 'The given data was invalid.', 'errors' => '{{errors}}'],
        );
    }
}
