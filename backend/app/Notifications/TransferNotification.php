<?php

namespace App\Notifications;

use App\Models\Transfer;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\Money;
use Illuminate\Notifications\Notification;

/**
 * إشعار تحويل واستلام العهدة: داخل التطبيق + Push + واتساب.
 */
class TransferNotification extends Notification
{
    public const MESSAGES = [
        'pending' => ['تحويل عهدة بانتظار استلامك', '%s حوّل لك عهدة بمبلغ %s — برجاء تأكيد الاستلام'],
        'accepted' => ['تم استلام العهدة ✓', '%s أكّد استلام عهدة بمبلغ %s'],
        'rejected' => ['تم رفض استلام العهدة', '%s رفض استلام العهدة بمبلغ %s'],
        'cancelled' => ['تم إلغاء تحويل العهدة', 'تم إلغاء تحويل عهدة بمبلغ %s من %s'],
    ];

    public function __construct(public Transfer $transfer, public string $event) {}

    public function via(object $notifiable): array
    {
        return array_filter([
            'database',
            WebPushChannel::class,
            WhatsAppChannel::enabled() ? WhatsAppChannel::class : null,
        ]);
    }

    public function toArray(object $notifiable): array
    {
        [$title, $bodyTemplate] = self::MESSAGES[$this->event] ?? ['إشعار عهدة', '%s'];
        $transfer = $this->transfer->loadMissing('from', 'to', 'paymentMethod');
        $method = $transfer->paymentMethod?->name;
        $via = $method ? " عبر ({$method})" : '';

        $body = match ($this->event) {
            'pending' => sprintf($bodyTemplate, $transfer->from->name, Money::format($transfer->amount)).$via,
            'accepted' => sprintf($bodyTemplate, $transfer->to->name, Money::format($transfer->amount)),
            'rejected' => sprintf($bodyTemplate, $transfer->to->name, Money::format($transfer->amount)),
            'cancelled' => sprintf($bodyTemplate, Money::format($transfer->amount), $transfer->from->name),
            default => '',
        };

        return [
            'event' => 'transfer_'.$this->event,
            'title' => $title,
            'body' => $body,
            'transfer_id' => $transfer->id,
            'status' => $transfer->status,
            'reason' => match ($this->event) {
                'rejected' => $transfer->rejection_reason,
                'cancelled' => $transfer->cancellation_reason,
                default => null,
            },
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['body'].($data['reason'] ? " — {$data['reason']}" : ''),
            'url' => '/transfers',
            'tag' => "transfer-{$this->transfer->id}",
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        return "{$data['title']}\n{$data['body']}".($data['reason'] ? "\nالسبب: {$data['reason']}" : '');
    }
}
