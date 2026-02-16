<?php
declare(strict_types=1);

namespace App\Payment;

final class Id
{
    public static function txn(): string
    {
        // Example output: txn_20260215_ab12cd34ef
        return 'txn_' . gmdate('Ymd') . '_' . bin2hex(random_bytes(5));
    }
}