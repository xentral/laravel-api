<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Exceptions;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidPageNumberQuery extends InvalidQuery
{
    public static function pageNumberMustNotBeNegative(string $parameter, int $requested): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            "Requested page number `{$requested}` in `{$parameter}` is invalid. The page number must not be negative - omit it or send 0 to get the first page.",
        );
    }

    public static function pageNumberMustBeAnInteger(string $parameter, mixed $requested): self
    {
        $described = is_scalar($requested) ? "`{$requested}`" : get_debug_type($requested);

        return new self(
            Response::HTTP_BAD_REQUEST,
            "Requested page number {$described} in `{$parameter}` is not an integer.",
        );
    }
}
