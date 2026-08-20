<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query\Exceptions;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidPageSizeQuery extends InvalidQuery
{
    public static function pageSizeMustBePositive(int $requested): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            "Requested page size `{$requested}` is invalid. The page size must be at least 1.",
        );
    }
}
