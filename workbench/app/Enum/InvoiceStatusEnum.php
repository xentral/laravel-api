<?php declare(strict_types=1);

namespace Workbench\App\Enum;

use Xentral\LaravelApi\Enum\HasInactiveCases;

enum InvoiceStatusEnum: string implements HasInactiveCases
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public static function getInactiveCases(): array
    {
        return [
            self::Draft,
            self::Cancelled,
        ];
    }

    public static function getActiveCases(): array
    {
        return [
            self::Sent,
            self::Paid,
            self::Overdue,
        ];
    }
}
