<?php

namespace App\Console\Commands;

use App\Models\Custodian;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mosque:create-imam {--name=} {--phone=} {--password=}')]
#[Description('ينشئ حساب الإمام الأول (ومعاه عهدة باسمه) عند بدء استخدام النظام')]
class CreateImam extends Command
{
    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('اسم الإمام');
        $phone = $this->option('phone') ?: $this->ask('رقم الموبايل');
        $password = $this->option('password') ?: $this->secret('كلمة المرور (8 حروف على الأقل)');

        if (! $name || ! $phone || strlen((string) $password) < 8) {
            $this->error('الاسم والموبايل مطلوبين، وكلمة المرور 8 حروف على الأقل.');

            return self::FAILURE;
        }

        if (User::where('phone', $phone)->exists()) {
            $this->error('رقم الموبايل ده متسجل قبل كده.');

            return self::FAILURE;
        }

        $custodian = Custodian::create(['name' => $name, 'title' => 'إمام المسجد', 'phone' => $phone]);
        $user = User::create(['name' => $name, 'phone' => $phone, 'password' => $password, 'custodian_id' => $custodian->id]);
        $user->assignRole(User::ROLE_IMAM);

        $this->info("تم إنشاء حساب {$name}. ادخل برقم {$phone} وضيف باقي المستخدمين من الإعدادات.");

        return self::SUCCESS;
    }
}
