<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Enum;

enum PaginationType: string
{
    case SIMPLE = 'simple';
    case TABLE = 'table';
    case CURSOR = 'cursor';
}
