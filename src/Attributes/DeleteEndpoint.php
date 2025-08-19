<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

use OpenApi\Annotations\Delete;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class DeleteEndpoint extends Delete
{
    use HasEndpointHelpers;

    public function __construct(
        string $path,
        ?string $description = null,
        ?array $tags = null,
        ?array $security = null,
        ?string $summary = null,
        ?array $parameters = [],
        ?string $operationId = null,
        array $validates = [],
        bool $isInternal = false,
        ?\DateTimeInterface $deprecated = null,
        \BackedEnum|string|null $featureFlag = null,
        string|array|null $scopes = null,
    ) {
        $responses = [
            $this->response('204', 'Resource successfully deleted'),
            ...$this->makeNegativeResponses(with404: true),
        ];

        $parameters = $this->makeParameters($parameters, $path);

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
            'x' => $this->compileX($isInternal, $deprecated, $featureFlag, $scopes, ! empty($validates) ? $validates : null),
            'value' => $this->combine($responses, $parameters),
        ]);
    }
}
