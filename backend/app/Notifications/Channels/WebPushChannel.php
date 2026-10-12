<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Push للأجهزة اللي فعّلت الإشعارات (PWA). الاشتراكات المنتهية بتتمسح تلقائيًا.
 * الإشعار لازم يكون فيه toWebPush($notifiable): ['title', 'body', 'url'].
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->getKey())->get();

        // فشل الـ Push ميوقفش العملية الأصلية (الموافقة/الطلب) — بيتسجل في الـ log بس
        foreach (self::deliver($subscriptions, $notification->toWebPush($notifiable)) as $result) {
            if (! $result['ok']) {
                Log::warning("Web push failed for subscription #{$result['id']}: {$result['reason']}");
            }
        }
    }

    public static function configured(): bool
    {
        return (bool) (config('services.webpush.public_key') && config('services.webpush.private_key'));
    }

    /**
     * يبعت لكل الاشتراكات ويرجّع نتيجة كل جهاز: [['id', 'ok', 'reason', 'device']].
     *
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    public static function deliver(Collection $subscriptions, array $payload): array
    {
        if ($subscriptions->isEmpty()) {
            return [];
        }

        if (! self::configured()) {
            return $subscriptions->map(fn ($s) => ['id' => $s->id, 'ok' => false, 'reason' => 'مفاتيح VAPID مش متظبطة على السيرفر', 'device' => self::device($s->endpoint)])->all();
        }

        try {
            $push = new WebPush(
                ['VAPID' => [
                    'subject' => config('services.webpush.subject'),
                    'publicKey' => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ]],
                // TTL يوم كامل: لو الموبايل مقفول أو من غير نت يوصله أول ما يفتح، و high عشان يظهر فورًا
                ['TTL' => 86400, 'urgency' => 'high'],
            );

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $byEndpoint = $subscriptions->keyBy('endpoint');

            foreach ($subscriptions as $sub) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => 'aes128gcm',
                ]), $json);
            }

            $results = [];
            foreach ($push->flush() as $report) {
                $sub = $byEndpoint[$report->getEndpoint()] ?? null;
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
                $results[] = [
                    'id' => $sub?->id,
                    'ok' => $report->isSuccess(),
                    'reason' => $report->isSuccess() ? null : ($report->isSubscriptionExpired() ? 'الاشتراك انتهى (اتمسح) — فعّل الإشعارات تاني على الجهاز ده' : $report->getReason()),
                    'device' => self::device($report->getEndpoint()),
                ];
            }

            return $results;
        } catch (Throwable $e) {
            return $subscriptions->map(fn ($s) => ['id' => $s->id, 'ok' => false, 'reason' => $e->getMessage(), 'device' => self::device($s->endpoint)])->all();
        }
    }

    private static function device(string $endpoint): string
    {
        return match (true) {
            str_contains($endpoint, 'fcm.googleapis.com') => 'أندرويد / Chrome',
            str_contains($endpoint, 'push.apple.com') => 'آيفون / Safari',
            str_contains($endpoint, 'mozilla.com') => 'Firefox',
            str_contains($endpoint, 'notify.windows.com') => 'Edge / ويندوز',
            default => 'متصفح',
        };
    }
}
