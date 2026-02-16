<?php
declare(strict_types=1);

namespace App\Payment;

final class StateMachine
{
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED   = 'captured';
    public const STATUS_VOIDED     = 'voided';
    public const STATUS_REFUNDED   = 'refunded';

    public static function canCapture(string $status): bool
    {
        return $status === self::STATUS_AUTHORIZED;
    }

    public static function canVoid(string $status): bool
    {
        return $status === self::STATUS_AUTHORIZED;
    }

    public static function canRefund(string $status): bool
    {
        return $status === self::STATUS_CAPTURED;
    }
}