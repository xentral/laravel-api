<?php declare(strict_types=1);
namespace Xentral\LaravelApi\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\Includes\IncludeInterface;

class DummyInclude implements IncludeInterface
{
    public function __invoke(Builder $query, string $include): void {}

    public static function make(string $name): Collection
    {
        // AllowedInclude::custom() already returns a Collection
        $includes = AllowedInclude::custom($name, new self);

        // If this is a nested include (contains dots), we need to ensure
        // the parent relationship is loaded so the resource can be rendered
        if (str_contains($name, '.')) {
            // Extract parent path (everything before the last dot)
            $parentPath = substr($name, 0, strrpos($name, '.'));

            // Add the parent relationship first so it loads before the dummy include
            $includes = AllowedInclude::relationship($parentPath)->merge($includes);
        }

        return $includes;
    }
}
