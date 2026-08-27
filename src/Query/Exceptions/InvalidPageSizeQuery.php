<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Exceptions;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidPageSizeQuery extends InvalidQuery
{
    public static function pageSizeMustNotBeNegative(string $parameter, int $requested): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            "Requested page size `{$requested}` in `{$parameter}` is invalid. The page size must not be negative - omit it or send 0 to get the default.",
        );
    }

    public static function pageSizeMustBeAnInteger(string $parameter, mixed $requested): self
    {
        $described = is_scalar($requested) ? "`{$requested}`" : get_debug_type($requested);

        return new self(
            Response::HTTP_BAD_REQUEST,
            "Requested page size {$described} in `{$parameter}` is not an integer.",
        );
    }

    /**
     * @param  array<string, mixed>  $requested  the sent spellings, keyed by parameter name
     */
    public static function pageSizeIsAmbiguous(array $requested): self
    {
        $spellings = implode(', ', array_map(
            fn (string $parameter, mixed $value): string => $parameter.'=`'.(is_scalar($value) ? $value : get_debug_type($value)).'`',
            array_keys($requested),
            $requested,
        ));

        return new self(
            Response::HTTP_BAD_REQUEST,
            "The requested page size is ambiguous: {$spellings}. Send a single page size parameter, or the same value in each.",
        );
    }
}
