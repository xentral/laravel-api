<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Xentral\LaravelApi\OpenApi\SchemaConfig;

class RateLimitResponseProcessor
{
    public function __construct(
        private readonly SchemaConfig $config
    ) {}

    public function __invoke(Analysis $analysis): void
    {
        if (! $this->config->rateLimitResponse->enabled) {
            return;
        }

        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            $rateLimitResponse = $this->createRateLimitResponse();

            // Add 429 response to the operation's responses
            $operation->responses[] = $rateLimitResponse;
        }
    }

    private function createRateLimitResponse(): Response
    {
        return new Response(
            response: '429',
            description: $this->config->rateLimitResponse->message,
            content: new MediaType(
                mediaType: 'application/json',
                schema: new Schema(
                    properties: [
                        new Property('message', type: 'string', example: $this->config->rateLimitResponse->message),
                    ],
                    type: 'object',
                )
            )
        );
    }
}
