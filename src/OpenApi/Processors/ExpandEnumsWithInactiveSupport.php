<?php declare(strict_types=1);

namespace Xentral\LaravelApi\OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\OpenApiException;
use OpenApi\Processors\ExpandEnums;
use ReflectionClass;
use Xentral\LaravelApi\Enum\HasInactiveCases;

class ExpandEnumsWithInactiveSupport extends ExpandEnums
{
    protected function expandSchemaEnum(Analysis $analysis): void
    {
        /** @var OA\Schema[] $schemas */
        $schemas = $analysis->getAnnotationsOfType([OA\Schema::class, OA\ServerVariable::class]);

        foreach ($schemas as $schema) {
            if (Generator::isDefault($schema->enum)) {
                continue;
            }
            $cases = [];

            if (is_string($schema->enum)) {
                // Check if it's an enum class-string that implements HasInactiveCases
                if (is_a($schema->enum, \UnitEnum::class, true)) {
                    $enumClass = $schema->enum;
                    // Check if it implements HasInactiveCases
                    if (class_exists($enumClass)) {
                        $reflection = new ReflectionClass($enumClass);
                        if ($reflection->implementsInterface(HasInactiveCases::class)) {
                            $cases = $enumClass::getActiveCases();
                        } else {
                            // Use all cases for regular enums
                            $cases = $enumClass::cases();
                        }
                    }
                } else {
                    throw new OpenApiException("Unexpected enum value, requires specifying the Enum class string: $schema->enum");
                }
            } else {
                // Handle array of enums - same logic as parent class
                assert(is_array($schema->enum));

                // Transform \UnitEnum into individual cases
                /** @var string|class-string<\UnitEnum> $enum */
                foreach ($schema->enum as $enum) {
                    if (is_string($enum) && function_exists('enum_exists') && enum_exists($enum)) {
                        // Check if this enum implements HasInactiveCases
                        if (class_exists($enum)) {
                            $reflection = new ReflectionClass($enum);
                            if ($reflection->implementsInterface(HasInactiveCases::class)) {
                                $cases = $enum::getActiveCases();
                            } else {
                                $cases = $enum::cases();
                            }
                        }
                    } else {
                        $cases[] = $enum;
                    }
                }
            }

            $enums = [];
            foreach ($cases as $enum) {
                $enums[] = is_a($enum, \UnitEnum::class) ? $enum->value ?? $enum->name : $enum;
            }

            $schema->enum = $enums;
        }
    }
}
