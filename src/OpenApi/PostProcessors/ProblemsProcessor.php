<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;

class ProblemsProcessor
{
    public function __construct() {}

    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (! isset($operation->x['problems']) || empty($operation->x['problems'])) {
                continue;
            }

            // Get the problems config from the schema config
            $problemsConfig = config('openapi.problems', []);

            foreach ($operation->x['problems'] as $problemKey) {
                if (! isset($problemsConfig[$problemKey])) {
                    continue;
                }

                $problemConfig = $problemsConfig[$problemKey];
                $problemResponse = $this->createProblemResponse($problemConfig);

                // Add the problem response to the operation
                $operation->responses[] = $problemResponse;
            }

            // Clean up the x['problems'] field
            unset($operation->x['problems']);
        }
    }

    private function createProblemResponse(array $config): Response
    {
        $properties = [];
        foreach ($config['body'] as $key => $value) {
            $properties[] = new Property(
                property: $key,
                type: 'string',
                example: $value
            );
        }

        return new Response(
            response: (string) $config['status'],
            description: $config['body']['title'] ?? 'Problem occurred',
            content: new MediaType(
                mediaType: 'application/json',
                schema: new Schema(
                    properties: $properties,
                    type: 'object'
                )
            )
        );
    }
}
