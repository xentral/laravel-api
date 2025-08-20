<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi;

use Xentral\LaravelApi\OpenApi\Config\DeprecationFilterConfig;
use Xentral\LaravelApi\OpenApi\Config\FeatureFlagsConfig;
use Xentral\LaravelApi\OpenApi\Config\PaginationResponseConfig;
use Xentral\LaravelApi\OpenApi\Config\ValidationResponseConfig;

readonly class SchemaConfig
{
    public function __construct(
        public string $oasVersion,
        public array $folders,
        public string $output,
        public string $locale,
        public ValidationResponseConfig $validationResponse,
        public PaginationResponseConfig $paginationResponse,
        public DeprecationFilterConfig $deprecationFilter,
        public FeatureFlagsConfig $featureFlags,
        public array $validationCommands,
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(
            oasVersion: $config['oas_version'] ?? '3.1.0',
            folders: $config['folders'],
            output: $config['output'],
            locale: $config['locale'] ?? 'en',
            validationResponse: ValidationResponseConfig::fromArray($config['validation_response'] ?? []),
            paginationResponse: PaginationResponseConfig::fromArray($config['pagination_response'] ?? []),
            deprecationFilter: DeprecationFilterConfig::fromArray($config['deprecation_filter'] ?? []),
            featureFlags: FeatureFlagsConfig::fromArray($config['feature_flags'] ?? []),
            validationCommands: $config['validation_commands'] ?? [],
        );
    }
}
