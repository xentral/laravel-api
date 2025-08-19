<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi\Filters;

interface FilterSpecCollection
{
    public function getFilterSpecification(): array;
}
