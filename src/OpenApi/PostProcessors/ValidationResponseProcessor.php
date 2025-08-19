<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\PostProcessors;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Response;
use OpenApi\Attributes\Schema;
use Xentral\LaravelApi\OpenApi\ValidationRuleExtractor;

class ValidationResponseProcessor
{
    public function __construct(
        private readonly int $validationStatusCode = 422,
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

        // Update existing validation response status codes to use the configured status code
        foreach ($allOperations as $operation) {
            if (! isset($operation->responses)) {
                continue;
            }
            foreach ($operation->responses as $response) {
                $status = (int) $response->response;
                if ($status === 422 || $status === 400) {
                    $response->response = (string) $this->validationStatusCode;
                }
            }
        }
    }

    public function createValidationResponse(null|string|array $request): ?Response
    {
        if ($request === null) {
            return null;
        }
        try {
            if (is_string($request)) {
                $rulesWithMessages = $this->ruleExtractor->extractRulesWithMessages($request);

                // Generate error messages from the extracted rules and messages
                $errorMessages = [];
                foreach ($rulesWithMessages as $field => $data) {
                    $errorMessages[$field] = [$data['message']];
                }
            } elseif (is_array($request)) {
                $errorMessages = [];

                // Check if array is associative (field => message)
                if (array_keys($request) !== range(0, count($request) - 1)) {
                    // Associative array - use keys as field names and values as custom messages
                    foreach ($request as $field => $message) {
                        $errorMessages[$field] = [is_array($message) ? $message[0] : $message];
                    }
                } else {
                    // Indexed array - use values as field names and generate messages
                    foreach ($request as $field) {
                        $errorMessages[$field] = [$this->generateLaravelLikeValidationMessage($field, 'required')];
                    }
                }
            }
        } catch (\Throwable) {
            $errorMessages = [];
        }
        // Fallback if no messages could be generated
        if (empty($errorMessages)) {
            $errorMessages = [
                'property' => ['The property is required.'],
            ];
        }

        return new Response(
            response: (string) $this->validationStatusCode,
            description: 'Failed validation',
            content: new MediaType(
                mediaType: 'application/problem+json',
                schema: new Schema(
                    properties: [
                        new Property('message', type: 'string',
                            example: reset($errorMessages)[0] ?? 'The validation failed.',
                        ),
                        new Property('errors', type: 'object',
                            example: $errorMessages,
                        ),
                    ],
                    type: 'object',
                )
            )
        );
    }

    public function generateLaravelLikeValidationMessage(string $field, string|array|ValidationRule|Rule $rule): string
    {
        if ($rule instanceof Rule) {
            // If it's a Rule instance, we can use its message method
            $rule = strtolower(class_basename($rule));
        }
        // Convert array rules to string for parsing
        if (is_array($rule)) {
            $rule = implode('|', array_filter($rule, fn ($v) => is_string($v)));
        }

        // Parse the rules
        $rulesList = explode('|', $rule);
        $formattedField = str_replace('_', ' ', $field);

        // Generate Laravel-like messages for common rules
        if (in_array('required', $rulesList)) {
            return "The {$formattedField} field is required.";
        }

        foreach ($rulesList as $singleRule) {
            if ($singleRule === 'email') {
                return "The {$formattedField} must be a valid email address.";
            }

            if (str_starts_with($singleRule, 'min:')) {
                $min = substr($singleRule, 4);

                return "The {$formattedField} must be at least {$min} characters.";
            }

            if (str_starts_with($singleRule, 'max:')) {
                $max = substr($singleRule, 4);

                return "The {$formattedField} may not be greater than {$max} characters.";
            }

            if ($singleRule === 'numeric') {
                return "The {$formattedField} must be a number.";
            }

            if ($singleRule === 'integer') {
                return "The {$formattedField} must be an integer.";
            }

            if ($singleRule === 'date') {
                return "The {$formattedField} is not a valid date.";
            }

            if ($singleRule === 'boolean') {
                return "The {$formattedField} field must be true or false.";
            }

            if (str_starts_with($singleRule, 'in:')) {
                $values = substr($singleRule, 3);

                return "The selected {$formattedField} is invalid.";
            }

            if (str_starts_with($singleRule, 'regex:')) {
                return "The {$formattedField} format is invalid.";
            }
        }

        // Default message
        return "The {$formattedField} is invalid.";
    }

    public function extractValidationInfo(string $requestClass): array
    {
        try {
            $rules = $this->ruleExtractor->extractRules($requestClass);

            // Get the first two rules for example purposes
            return array_slice($rules, 0, 2, true);
        } catch (\Throwable) {
            // Silently fail and return empty array
            return [];
        }
    }
}
