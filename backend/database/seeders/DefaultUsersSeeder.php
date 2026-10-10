<?php

namespace Database\Seeders;

use App\Models\Custodian;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $custodiansData = [
            ['name' => 'الشيخ أحمد', 'title' => 'إمام المسجد', 'phone' => '01000000001'],
            ['name' => 'الحاج محمود', 'title' => 'أمين الصندوق', 'phone' => '01000000002'],
            ['name' => 'الأستاذ خالد', 'title' => 'المحاسب', 'phone' => '01000000003'],
            ['name' => 'عم سيد', 'title' => 'عامل ومسؤول الخدمات', 'phone' => '01023456789'],
        ];

        $custodians = [];
        foreach ($custodiansData as $cd) {
            $custodians[$cd['phone']] = Custodian::firstOrCreate(
                ['phone' => $cd['phone']],
                ['name' => $cd['name'], 'title' => $cd['title'], 'is_active' => true]
            );
        }

        $usersData = [
            [
                'name' => 'الشيخ أحمد',
                'phone' => '01000000001',
                'role' => User::ROLE_IMAM,
                'password' => '12345678',
            ],
            [
                'name' => 'الحاج محمود',
                'phone' => '01000000002',
                'role' => User::ROLE_TREASURER,
                'password' => '12345678',
            ],
            [
                'name' => 'الأستاذ خالد',
                'phone' => '01000000003',
                'role' => User::ROLE_ACCOUNTANT,
                'password' => '12345678',
            ],
        ];

        foreach ($usersData as $ud) {
            $custodian = $custodians[$ud['phone']] ?? null;

            $user = User::where('phone', $ud['phone'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => $ud['name'],
                    'phone' => $ud['phone'],
                    'password' => Hash::make($ud['password']),
                    'is_active' => true,
                    'custodian_id' => $custodian?->id,
                ]);
            } else {
                // التأكد من تفعيل الحساب وتحديث كلمة المرور لضمان إمكانية الدخول
                $user->update([
                    'is_active' => true,
                    'password' => Hash::make($ud['password']),
                    'custodian_id' => $custodian?->id ?? $user->custodian_id,
                ]);
            }

            if (! $user->hasRole($ud['role'])) {
                $user->syncRoles([$ud['role']]);
            }
        }
    }
}
