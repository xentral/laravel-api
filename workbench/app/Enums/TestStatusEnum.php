<?php declare(strict_types=1);

namespace Workbench\App\Enums;

enum TestStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';

    public const MAPPING = [
        1 => self::ACTIVE,
        2 => self::INACTIVE,
        3 => self::PENDING,
    ];
}
