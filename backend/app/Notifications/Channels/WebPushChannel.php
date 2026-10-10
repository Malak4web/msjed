<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use Illuminate\Notifications\Notification;
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
        $config = config('services.webpush');
        if (! $config['public_key'] || ! $config['private_key'] || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->getKey())->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $push = new WebPush(['VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['public_key'],
                'privateKey' => $config['private_key'],
            ]]);

            $payload = json_encode($notification->toWebPush($notifiable), JSON_UNESCAPED_UNICODE);

            foreach ($subscriptions as $sub) {
                $push->queueNotification(Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                ]), $payload);
            }

            foreach ($push->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        } catch (Throwable $e) {
            // فشل الـ Push ميوقفش العملية الأصلية (الموافقة/الطلب)
            Log::warning('Web push failed: '.$e->getMessage());
        }
    }
}
