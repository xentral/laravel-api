<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

use OpenApi\Annotations\Get;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class GetEndpoint extends Get
{
    use HasEndpointHelpers;

    public function __construct(
        string $path,
        string $resource,
        ?string $description = null,
        ?array $tags = null,
        ?array $security = null,
        ?string $summary = null,
        ?array $parameters = [],
        ?string $operationId = null,
        array $includes = [],
        bool $isInternal = false,
        ?\DateTimeInterface $deprecated = null,
        \BackedEnum|string|null $featureFlag = null,
        string|array|null $scopes = null,
        ?array $problems = null,
    ) {
        $responses = [
            $this->resourceResponse('200', $description, $resource),
            ...$this->makeNegativeResponses(with404: true),
        ];

        $parameters = $this->makeParameters($parameters, $path, includes: $includes);

        parent::__construct([
            'path' => $path,
            'operationId' => $operationId ?? Generator::UNDEFINED,
            'description' => $description ?? Generator::UNDEFINED,
            'summary' => $summary ?? Generator::UNDEFINED,
            'security' => $security ?? Generator::UNDEFINED,
            'servers' => Generator::UNDEFINED,
            'tags' => $tags ?? Generator::UNDEFINED,
            'callbacks' => Generator::UNDEFINED,
            'deprecated' => $deprecated !== null ? true : Generator::UNDEFINED,
            'x' => $this->compileX($isInternal, $deprecated, $featureFlag, $scopes, null, $problems),
            'value' => $this->combine($responses, $parameters),
        ]);
    }
}
