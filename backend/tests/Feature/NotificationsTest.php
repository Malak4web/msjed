<?php

namespace Tests\Feature;

use App\Models\PushSubscription;

class NotificationsTest extends FinanceTestCase
{
    public function test_imam_gets_notification_and_can_mark_read(): void
    {
        config(['services.webpush.public_key' => null]); // من غير Push حقيقي في الاختبار
        $this->addIncome($this->sayed, 1000);
        $this->actingAsUser($this->treasurer)->postJson('/api/expense-requests', [
            'custodian_id' => $this->sayed->id,
            'expense_category_id' => $this->category()->id,
            'amount' => 200,
            'description' => 'مطهرات',
        ])->assertCreated();

        $list = $this->actingAsUser($this->imam)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.event', 'submitted')
            ->assertJsonPath('data.0.title', 'طلب صرف جديد محتاج موافقتك');

        $id = $list->json('data.0.id');
        $this->actingAsUser($this->imam)->postJson("/api/notifications/{$id}/read")->assertOk()->assertJsonPath('unread', 0);
        $this->actingAsUser($this->imam)->getJson('/api/auth/me')->assertJsonPath('user.unread_notifications', 0);

        // المستخدم مايقدرش يقرا إشعار غيره
        $this->actingAsUser($this->treasurer)->postJson("/api/notifications/{$id}/read")->assertNotFound();
    }

    public function test_push_subscription_lifecycle(): void
    {
        $sub = ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'auth']];

        $this->actingAsUser($this->imam)->postJson('/api/push/subscribe', $sub)->assertCreated();
        $this->actingAsUser($this->imam)->postJson('/api/push/subscribe', $sub)->assertCreated();
        $this->assertSame(1, PushSubscription::count(), 'نفس الجهاز ميتسجلش مرتين');

        $this->actingAsUser($this->imam)->postJson('/api/push/unsubscribe', ['endpoint' => $sub['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_push_test_endpoint_reports_missing_devices_and_per_device_results(): void
    {
        $this->actingAsUser($this->imam)->postJson('/api/push/test')
            ->assertStatus(422)->assertJsonPath('devices', 0);

        PushSubscription::create(['user_id' => $this->imam->id, 'endpoint' => 'https://invalid.invalid/push', 'public_key' => 'bad', 'auth_token' => 'bad']);

        $this->actingAsUser($this->imam)->postJson('/api/push/test')
            ->assertStatus(502)
            ->assertJsonPath('devices', 1)
            ->assertJsonPath('results.0.ok', false);
    }

    public function test_failed_push_does_not_break_the_request_flow(): void
    {
        PushSubscription::create(['user_id' => $this->imam->id, 'endpoint' => 'https://invalid.invalid/push', 'public_key' => 'bad', 'auth_token' => 'bad']);
        $this->addIncome($this->sayed, 1000);

        $this->actingAsUser($this->treasurer)->postJson('/api/expense-requests', [
            'custodian_id' => $this->sayed->id,
            'expense_category_id' => $this->category()->id,
            'amount' => 100,
            'description' => 'لمبات',
        ])->assertCreated();
    }
}
