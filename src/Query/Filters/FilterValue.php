<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Filters;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Coerces and validates raw filter input before it reaches a query.
 *
 * Filters that build their own predicate - including relation based ones outside
 * this package - should reuse these helpers instead of re-implementing the
 * accepted value spellings and their error messages.
 */
final class FilterValue
{
    public static function toBool(mixed $value, string $property): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        throw ValidationException::withMessages([
            $property => 'Invalid value: '.self::printable($value).'. Valid values are: true, false.',
        ]);
    }

    /**
     * @return non-empty-list<int>
     */
    public static function toIds(mixed $value, string $property): array
    {
        $ids = array_values(Arr::wrap($value));

        if ($ids === []) {
            throw ValidationException::withMessages([
                $property => 'Invalid value: []. Expected one or more numeric ids.',
            ]);
        }

        return array_map(static function (mixed $id) use ($property): int {
            if (! is_numeric($id)) {
                throw ValidationException::withMessages([
                    $property => 'Invalid value: '.self::printable($id).'. Expected one or more numeric ids.',
                ]);
            }

            return (int) $id;
        }, $ids);
    }

    private static function printable(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : gettype($value);
    }
}
