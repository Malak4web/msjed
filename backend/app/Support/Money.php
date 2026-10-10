<?php

namespace App\Support;

/**
 * الحسابات بالقروش (أعداد صحيحة) لتجنّب أخطاء الـ float.
 */
class Money
{
    public static function cents(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** عرض للمستخدم: 12,500 ج.م */
    public static function format(string|int|float|null $amount, bool $withCurrency = true): string
    {
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) == 0.0 ? 0 : 2;
        $text = number_format($value, $decimals);

        return $withCurrency ? $text.' ج.م' : $text;
    }
}
