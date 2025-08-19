<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Endpoints;

use OpenApi\Annotations\Post;
use OpenApi\Attributes\Property;
use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class PostEndpoint extends Post
{
    use HasEndpointHelpers;

    public function __construct(
        string $path,
        ?string $request = null,
        ?string $resource = null,
        ?string $description = null,
        ?array $tags = null,
        ?array $security = null,
        ?string $summary = null,
        ?array $parameters = [],
        ?string $operationId = null,
        string $successStatus = '200',
        bool $isInternal = false,
        ?\DateTimeInterface $deprecated = null,
        \BackedEnum|string|null $featureFlag = null,
        string|array|null $scopes = null,
    ) {
        $responses = [
            $resource
                ? $this->response($successStatus, $description, [new Property('data', ref: $resource)])
                : $this->response204(),
            ...$this->makeNegativeResponses(),
        ];

        $parameters = $this->makeParameters($parameters, $path);

        $requestBody = $this->makeRequestBody($request);

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
            'x' => $this->compileX($isInternal, $deprecated, $featureFlag, $scopes, $request),
            'value' => $this->combine($requestBody, $responses, $parameters),
        ]);
    }
}
