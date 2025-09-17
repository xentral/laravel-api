<?php declare(strict_types=1);

namespace Workbench\App\Enum;

use Xentral\LaravelApi\Enum\HasInactiveCases;

enum StatusEnum: string implements HasInactiveCases
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';

    public static function getInactiveCases(): array
    {
        return [
            self::PENDING,
        ];
    }

    public static function getActiveCases(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
        ];
    }
}
