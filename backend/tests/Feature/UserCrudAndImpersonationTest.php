<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

class UserCrudAndImpersonationTest extends FinanceTestCase
{
    public function test_imam_can_list_and_view_users(): void
    {
        $response = $this->actingAsUser($this->imam)->getJson('/api/users');
        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'phone', 'role', 'is_active']]]);

        $showResponse = $this->actingAsUser($this->imam)->getJson("/api/users/{$this->treasurer->id}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('user.name', $this->treasurer->name);
    }

    public function test_imam_can_create_new_user(): void
    {
        $response = $this->actingAsUser($this->imam)->postJson('/api/users', [
            'name' => 'مستخدم جديد',
            'phone' => '01099999999',
            'password' => 'secret1234',
            'role' => 'treasurer',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['phone' => '01099999999', 'name' => 'مستخدم جديد']);
    }

    public function test_imam_can_update_user(): void
    {
        $response = $this->actingAsUser($this->imam)->putJson("/api/users/{$this->treasurer->id}", [
            'name' => 'الحاج محمود بعد التعديل',
            'phone' => $this->treasurer->phone,
            'role' => 'accountant',
            'is_active' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $this->treasurer->id, 'name' => 'الحاج محمود بعد التعديل']);
        $this->assertTrue($this->treasurer->fresh()->hasRole('accountant'));
    }

    public function test_cannot_delete_self(): void
    {
        $response = $this->actingAsUser($this->imam)->deleteJson("/api/users/{$this->imam->id}");
        $response->assertStatus(422);
        $response->assertJson(['message' => 'لا يمكنك حذف الحساب الذي قمت بتسجيل الدخول به حالياً.']);
    }

    public function test_cannot_delete_only_imam(): void
    {
        // إذا حاول مستخدم آخر ذو صلاحية حذف الإمام الوحيد
        $response = $this->actingAsUser($this->accountant)->deleteJson("/api/users/{$this->imam->id}");
        // غير الإمام ممنوع يحذف الإمام
        $response->assertStatus(422);
    }

    public function test_cannot_delete_user_with_transactions(): void
    {
        // إضافة إيراد مسجل بواسطة أمين الصندوق
        $this->addIncome($this->mahmoud, 1000);

        $response = $this->actingAsUser($this->imam)->deleteJson("/api/users/{$this->treasurer->id}");
        $response->assertStatus(422);
        $this->assertStringContainsString('لا يمكن حذف هذا المستخدم لوجود معاملات مالية', $response->json('message'));
        $this->assertDatabaseHas('users', ['id' => $this->treasurer->id]);
    }

    public function test_can_delete_user_without_transactions(): void
    {
        // مستخدم جديد بدون أي قيود
        $newUser = $this->user(User::ROLE_TREASURER);

        $response = $this->actingAsUser($this->imam)->deleteJson("/api/users/{$newUser->id}");
        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $newUser->id]);
    }

    public function test_imam_can_impersonate_any_active_user(): void
    {
        $response = $this->actingAsUser($this->imam)->postJson("/api/users/{$this->treasurer->id}/impersonate");
        $response->assertOk();
        $response->assertJsonStructure(['token', 'user', 'message']);
        $this->assertEquals($this->treasurer->id, $response->json('user.id'));
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_non_imam_cannot_impersonate_users(): void
    {
        $response = $this->actingAsUser($this->accountant)->postJson("/api/users/{$this->treasurer->id}/impersonate");
        $response->assertForbidden();
    }

    public function test_imam_cannot_impersonate_self(): void
    {
        $response = $this->actingAsUser($this->imam)->postJson("/api/users/{$this->imam->id}/impersonate");
        $response->assertStatus(422);
        $response->assertJson(['message' => 'أنت مسجل الدخول بهذا الحساب بالفعل.']);
    }

    public function test_cannot_impersonate_inactive_user(): void
    {
        $inactiveUser = $this->user(User::ROLE_TREASURER);
        $inactiveUser->update(['is_active' => false]);

        $response = $this->actingAsUser($this->imam)->postJson("/api/users/{$inactiveUser->id}/impersonate");
        $response->assertStatus(422);
        $this->assertJsonStringEqualsJsonString(
            json_encode(['message' => 'لا يمكن تسجيل الدخول بحساب معطّل. قم بتفعيل الحساب أولاً.']),
            $response->getContent()
        );
    }
}
