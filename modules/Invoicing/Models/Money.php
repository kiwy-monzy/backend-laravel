<?php

namespace Modules\Invoicing\Models;

/**
 * Minor-unit money helpers.
 *
 * Everything in this module stores integers and converts only at the edges —
 * reading a form, printing a total. Keeping the conversion in one place is
 * what stops a stray `* 100` from appearing twice and rounding differently.
 */
final class Money
{
    /** Form input ("1,250.50") to minor units (125050). */
    public static function toMinor(mixed $value): int
    {
        $clean = is_string($value) ? str_replace([',', ' '], '', $value) : $value;

        // round() before casting: (int)(1.15 * 100) is 114 on a binary float,
        // which is a cent lost on every line of every invoice.
        return (int) round(((float) $clean) * 100);
    }

    public static function toDecimal(int $minor): float
    {
        return $minor / 100;
    }

    public static function format(int $minor, string $currency = 'TZS'): string
    {
        return $currency . ' ' . number_format($minor / 100, 2);
    }
}
