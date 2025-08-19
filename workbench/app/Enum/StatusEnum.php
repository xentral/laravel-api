<?php declare(strict_types=1);
namespace Workbench\App\Enum;

enum StatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';

}
