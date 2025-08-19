<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Xentral\LaravelApi\OpenApi\SchemaConfig;
use Xentral\LaravelApi\OpenApi\ValidationRuleExtractor;

class ValidationResponseProcessor
{
    public function __construct(
        private readonly SchemaConfig $config,
        private readonly ValidationRuleExtractor $ruleExtractor = new ValidationRuleExtractor
    ) {}

    public function __invoke(Analysis $analysis): void
    {
        $allOperations = $analysis->getAnnotationsOfType(OA\Operation::class);

        /** @var OA\Operation $operation */
        foreach ($allOperations as $operation) {
            if (! isset($operation->x['request'])) {
                continue;
            }
            // Generate validation response and add it to the operation
            $validationResponse = $this->createValidationResponse($operation->x['request']);
            if ($validationResponse !== null) {
                // Insert validation response at the beginning of error responses
                // Find the position to insert (after success responses, before auth errors)
                $responses = $operation->responses;
                $insertPosition = 1; // After the first response (usually 200/201/204)

                array_splice($responses, $insertPosition, 0, [$validationResponse]);
                $operation->responses = $responses;
            }

            unset($operation->x['request']);
        }
    }

    public function createValidationResponse(null|string|array $request): ?Response
    {
        if ($request === null) {
            return null;
        }
        if (is_string($request)) {
            $rulesWithMessages = $this->ruleExtractor->extractRulesWithMessages($request);

            // Generate error messages from the extracted rules and messages
            $errorMessages = [];
            foreach ($rulesWithMessages as $field => $data) {
                $errorMessages[$field] = [$data['message']];
            }
        } elseif (is_array($request)) {
            $errorMessages = array_map(fn ($message) => [is_array($message) ? $message[0] : $message], $request);
        }
        // Fallback if no messages could be generated
        if (empty($errorMessages)) {
            $errorMessages = [
                'property' => ['The property is required.'],
            ];
        }

        $props = [];
        foreach ($this->config->validationResponse->content as $key => $value) {
            $props[] = $value === '{{errors}}'
                ? new Property($key, type: 'object', example: $errorMessages)
                : new Property($key, type: 'string', example: $value);
        }

        return new Response(
            response: (string) $this->config->validationResponse->statusCode,
            description: 'Failed validation',
            content: new MediaType(
                mediaType: $this->config->validationResponse->contentType,
                schema: new Schema(
                    properties: $props,
                    type: 'object',
                )
            )
        );
    }

    public function extractValidationInfo(string $requestClass): array
    {
        $rules = $this->ruleExtractor->extractRules($requestClass);

        // Get the first two rules for example purposes
        return array_slice($rules, 0, 2, true);
    }
}
