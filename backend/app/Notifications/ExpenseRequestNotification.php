<?php

namespace App\Notifications;

use App\Models\ExpenseRequest;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Support\Money;
use Illuminate\Notifications\Notification;

/**
 * إشعار طلب الصرف: داخل التطبيق + Push للأجهزة + واتساب (لما يتفعّل).
 */
class ExpenseRequestNotification extends Notification
{
    public const MESSAGES = [
        'submitted' => ['طلب صرف جديد محتاج موافقتك', '%s طلب %s لـ "%s"'],
        'approved' => ['اتوافق على طلبك ✓', 'طلب %2$s لـ "%3$s" اتوافق عليه، تقدر تنفّذ'],
        'rejected' => ['طلبك اترفض', 'طلب %2$s لـ "%3$s" اترفض'],
        'returned' => ['طلبك رجع للتعديل', 'طلب %2$s لـ "%3$s" محتاج تعديل'],
    ];

    public function __construct(public ExpenseRequest $request, public string $event) {}

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
        [$title, $body] = self::MESSAGES[$this->event];
        $request = $this->request->loadMissing('custodian');

        return [
            'event' => $this->event,
            'title' => $title,
            'body' => sprintf($body, $request->custodian->name, Money::format($request->amount), $request->description),
            'expense_request_id' => $request->id,
            'status' => $request->status,
            'reason' => match ($this->event) {
                'rejected' => $request->rejection_reason,
                'returned' => $request->return_reason,
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
            'url' => "/requests?status={$this->request->status}&id={$this->request->id}",
            'tag' => "request-{$this->request->id}",
        ];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        return "{$data['title']}\n{$data['body']}".($data['reason'] ? "\nالسبب: {$data['reason']}" : '');
    }
}
