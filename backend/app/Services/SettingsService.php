<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const DEFAULTS = [
        'mosque_name' => 'مسجد خالد بن الوليد',
        'mosque_address' => '',
        'mosque_phone' => '',
        'logo_path' => null,
        // a5 | thermal
        'receipt_format' => 'a5',
        // طلب صرف الإمام نفسه: auto (موافقة تلقائية) | accountant (يحتاج موافقة المحاسب)
        'imam_self_request_mode' => 'auto',
        'require_invoice_on_execute' => true,
        'show_hijri_date' => true,
    ];

    public function all(): array
    {
        $stored = Cache::rememberForever('settings.all', fn () => Setting::all()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $setting = Setting::firstOrNew(['key' => $key]);
            $setting->value = $value;
            $setting->save();
        }

        Cache::forget('settings.all');
    }
}
