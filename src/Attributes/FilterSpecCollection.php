<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Attributes;

interface FilterSpecCollection
{
    public function getFilterSpecification(): array;
}
