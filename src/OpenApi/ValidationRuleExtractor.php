<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use ReflectionMethod;
use Spatie\LaravelData\Data;

class ValidationRuleExtractor
{
    public function __construct(
        private readonly int $maxRules = 3
    ) {}

    public function extractRules(string $className): array
    {
        if (! class_exists($className)) {
            return [];
        }

        $ref = new ReflectionClass($className);

        return match (true) {
            $ref->isSubclassOf(FormRequest::class) => $this->extractFromFormRequest($ref),
            $ref->isSubclassOf(Data::class) => $this->extractFromDataObject($ref),
            default => [],
        };
    }

    public function extractRulesWithMessages(string $className): array
    {
        $allRules = $this->extractRules($className);
        $filteredRules = $this->filterNonDatabaseRules($allRules);
        $limitedRules = array_slice($filteredRules, 0, $this->maxRules, true);

        $result = [];
        foreach ($limitedRules as $field => $rules) {
            $result[$field] = [
                'rules' => $rules,
                'message' => $this->generateValidationMessage((string) $field, $rules),
            ];
        }

        return $result;
    }

    /** @param ReflectionClass<FormRequest> $reflection */
    private function extractFromFormRequest(ReflectionClass $reflection): array
    {
        if (! $reflection->hasMethod('rules')) {
            return [];
        }

        $rulesMethod = new ReflectionMethod($reflection->getName(), 'rules');
        $instance = $reflection->newInstanceWithoutConstructor();
        $rules = $rulesMethod->invoke($instance);

        if (! is_array($rules)) {
            return [];
        }

        return $this->normalizeRules($rules);
    }

    /** @param ReflectionClass<Data> $reflection */
    private function extractFromDataObject(ReflectionClass $reflection): array
    {
        if ($reflection->hasMethod('getValidationRules')) {
            $rules = call_user_func([$reflection->getName(), 'getValidationRules'], []);

            return $this->normalizeRules(is_array($rules) ? $rules : []);
        }

        return [];
    }

    private function filterNonDatabaseRules(array $rules): array
    {
        $filtered = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_numeric($field)) {
                continue;
            }
            $hasDatabaseRule = false;

            // Check if any rule in this field is a database rule
            foreach ($fieldRules as $rule) {
                if ($this->isDatabaseRule($rule)) {
                    $hasDatabaseRule = true;
                    break;
                }
            }

            // Only include the field if it has no database rules
            if (! $hasDatabaseRule) {
                $filtered[$field] = $fieldRules;
            }
        }

        return $filtered;
    }

    private function isDatabaseRule(mixed $rule): bool
    {
        if (is_string($rule)) {
            $databaseRules = ['exists', 'unique'];
            foreach ($databaseRules as $dbRule) {
                if (str_starts_with($rule, $dbRule.':')) {
                    return true;
                }
            }

            return false;
        }

        if (is_object($rule)) {
            $className = $rule::class;
            $databaseRuleClasses = [
                \Illuminate\Validation\Rules\Exists::class,
                \Illuminate\Validation\Rules\Unique::class,
            ];

            foreach ($databaseRuleClasses as $dbRuleClass) {
                if ($rule instanceof $dbRuleClass || $className === $dbRuleClass) {
                    return true;
                }
            }
        }

        return false;
    }

    private function generateValidationMessage(string $field, array $rules): string
    {
        $dummyValue = $this->getDummyValueForRules($rules);
        $dummyData = [$field => $dummyValue];

        $validator = Validator::make($dummyData, [$field => $rules]);

        if ($validator->fails()) {
            $messages = $validator->errors();
            $firstMessage = $messages->first($field);

            if ($firstMessage) {
                return $firstMessage;
            }
        }

        $formattedField = str_replace('_', ' ', $field);

        return "The {$formattedField} field is invalid.";
    }

    private function getDummyValueForRules(array $rules): mixed
    {
        // Check for required rule - use null to trigger required error
        if (in_array('required', $rules)) {
            return null;
        }

        // For other rules, try to provide a value that will fail validation
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                // For string rule, provide non-string value
                if ($rule === 'string') {
                    return 123; // integer will fail string validation
                }

                // For email rule, provide invalid email
                if ($rule === 'email') {
                    return 'invalid-email';
                }

                // For min: rules, provide empty string
                if (str_starts_with($rule, 'min:')) {
                    return '';
                }

                // For max: rules, provide long string
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);

                    return str_repeat('x', $max + 1);
                }

                // For numeric/integer rules, provide string
                if (in_array($rule, ['numeric', 'integer'])) {
                    return 'not-a-number';
                }

                // For date rule, provide invalid date
                if ($rule === 'date') {
                    return 'not-a-date';
                }

                // For boolean rule, provide string
                if ($rule === 'boolean') {
                    return 'not-a-boolean';
                }

                // For in: rules, provide value not in the list
                if (str_starts_with($rule, 'in:')) {
                    return 'not-in-list';
                }
            }
        }

        // Default to integer which will fail most string-based validation rules
        return 123;
    }

    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                // Convert pipe-separated string rules to array
                $normalized[$field] = explode('|', $fieldRules);
            } elseif (is_array($fieldRules)) {
                $normalized[$field] = $fieldRules;
            } else {
                // Single rule object (like Rule::enum())
                $normalized[$field] = [$fieldRules];
            }
        }

        return $normalized;
    }
}
