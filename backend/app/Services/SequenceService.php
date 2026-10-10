<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * أرقام مسلسلة بدون تكرار حتى مع الطلبات المتزامنة (لازم تتنادى جوه transaction).
 */
class SequenceService
{
    public function next(string $prefix, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $key = "{$prefix}-{$year}";

        $row = DB::table('sequences')->where('key', $key)->lockForUpdate()->first();

        if ($row) {
            $value = $row->value + 1;
            DB::table('sequences')->where('key', $key)->update(['value' => $value]);
        } else {
            $value = 1;
            DB::table('sequences')->insert(['key' => $key, 'value' => $value]);
        }

        return sprintf('%s-%d-%05d', $prefix, $year, $value);
    }
}
