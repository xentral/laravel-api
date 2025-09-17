<?php declare(strict_types=1);

namespace Xentral\LaravelApi\Enum;

interface HasInactiveCases
{
    public static function getInactiveCases(): array;

    public static function getActiveCases(): array;
}
