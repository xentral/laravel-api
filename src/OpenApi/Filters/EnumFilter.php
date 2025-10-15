<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

use Xentral\LaravelApi\Query\Filters\FilterOperator;

/**
 * Filter for enum values in OpenAPI specifications.
 *
 * This filter allows you to define enum constraints for query parameters.
 * You can provide either a BackedEnum class name or an array of allowed values.
 *
 * @example Using with BackedEnum:
 * enum Status: string {
 *     case ACTIVE = 'active';
 *     case INACTIVE = 'inactive';
 * }
 *
 * new EnumFilter(
 *     name: 'status',
 *     enumSource: Status::class,
 * )
 * @example Using with array of values:
 * new EnumFilter(
 *     name: 'category',
 *     enumSource: ['electronics', 'clothing', 'food'],
 * )
 */
#[\Attribute]
class EnumFilter extends FilterProperty
{
    public function __construct(
        string $name,
        string|array $enumSource,
        array $operators = [
            FilterOperator::EQUALS,
            FilterOperator::NOT_EQUALS,
            FilterOperator::IN,
            FilterOperator::NOT_IN,
        ],
    ) {
        $enumValues = $this->resolveEnumValues($enumSource);

        parent::__construct(
            name: $name,
            type: 'string',
            operators: $operators,
            enum: $enumValues,
        );
    }

    private function resolveEnumValues(string|array $enum): array
    {
        if (is_array($enum)) {
            return $enum;
        }
        if (! enum_exists($enum)) {
            throw new \InvalidArgumentException("Enum not found: {$enum}");
        }
        if (! is_subclass_of($enum, \BackedEnum::class)) {
            throw new \InvalidArgumentException("Enum not a \BackedEnum: {$enum}");
        }

        return array_map(fn (\BackedEnum $case) => $case->value, $enum::cases());
    }
}
