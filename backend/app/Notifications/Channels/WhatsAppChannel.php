<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * قناة واتساب جاهزة للتفعيل (مش شغالة دلوقتي).
 * للتفعيل: WHATSAPP_ENABLED=true و WHATSAPP_API_URL و WHATSAPP_API_TOKEN في .env،
 * وظبّط شكل الـ payload حسب المزوّد (Meta Cloud API / Twilio / UltraMsg...).
 * الإشعار لازم يكون فيه toWhatsApp($notifiable): string.
 */
class WhatsAppChannel
{
    public static function enabled(): bool
    {
        return (bool) config('services.whatsapp.enabled');
    }

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone ?? null;
        if (! self::enabled() || ! $phone || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        try {
            Http::withToken(config('services.whatsapp.token'))
                ->timeout(10)
                ->post(config('services.whatsapp.url'), [
                    'to' => '2'.ltrim($phone, '+'), // أرقام مصر: 01xxxxxxxxx → 201xxxxxxxxx
                    'body' => $notification->toWhatsApp($notifiable),
                ])->throw();
        } catch (Throwable $e) {
            Log::warning('WhatsApp notification failed: '.$e->getMessage());
        }
    }
}
