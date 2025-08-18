<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Http;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludeInterface;

class DummyInclude implements IncludeInterface
{
    public function __invoke(Builder $query, string $include): void {}

    public static function make(string $name): Collection
    {
        return AllowedInclude::custom($name, new self);
    }
}
