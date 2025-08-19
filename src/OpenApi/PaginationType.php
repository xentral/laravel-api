<?php declare(strict_types=1);
namespace Xentral\LaravelApi\OpenApi;

enum PaginationType: string
{
    case SIMPLE = 'simple';
    case TABLE = 'table';
    case CURSOR = 'cursor';
}
