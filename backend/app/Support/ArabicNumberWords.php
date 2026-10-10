<?php

namespace App\Support;

/**
 * تحويل المبلغ لحروف عربي: 1250.50 → "فقط ألف ومائتان وخمسون جنيهًا مصريًا وخمسون قرشًا لا غير"
 */
class ArabicNumberWords
{
    private const ONES = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];

    private const TEENS = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];

    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    /** [مفرد، مثنى، جمع (3-10)، تمييز (11+)] */
    private const SCALES = [
        1_000_000_000 => ['مليار', 'ملياران', 'مليارات', 'مليار'],
        1_000_000 => ['مليون', 'مليونان', 'ملايين', 'مليون'],
        1_000 => ['ألف', 'ألفان', 'آلاف', 'ألف'],
    ];

    public static function money(string|float|int $amount): string
    {
        $cents = Money::cents($amount);
        $pounds = intdiv($cents, 100);
        $piasters = $cents % 100;

        $text = 'فقط '.($pounds === 0 ? 'صفر' : self::number($pounds)).' جنيه مصري';

        if ($piasters > 0) {
            $text .= ' و'.self::number($piasters).' قرش';
        }

        return $text.' لا غير';
    }

    public static function number(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }

        $parts = [];

        foreach (self::SCALES as $value => $names) {
            if ($n >= $value) {
                $count = intdiv($n, $value);
                $n %= $value;
                $parts[] = match (true) {
                    $count === 1 => $names[0],
                    $count === 2 => $names[1],
                    $count <= 10 => self::belowThousand($count).' '.$names[2],
                    default => self::belowThousand($count).' '.$names[3],
                };
            }
        }

        if ($n > 0) {
            $parts[] = self::belowThousand($n);
        }

        return implode(' و', $parts);
    }

    private static function belowThousand(int $n): string
    {
        $parts = [];

        if ($n >= 100) {
            $parts[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }

        if ($n > 0) {
            $parts[] = match (true) {
                $n < 10 => self::ONES[$n],
                $n < 20 => self::TEENS[$n - 10],
                $n % 10 === 0 => self::TENS[intdiv($n, 10)],
                default => self::ONES[$n % 10].' و'.self::TENS[intdiv($n, 10)],
            };
        }

        return implode(' و', $parts);
    }
}
