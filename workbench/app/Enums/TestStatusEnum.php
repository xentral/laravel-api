<?php declare(strict_types=1);

namespace Workbench\App\Enums;

enum TestStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';

    public const MAPPING = [
        'old_value1' => self::ACTIVE,
        'old_value2' => self::INACTIVE,
        'old_value3' => self::PENDING,
    ];
}
