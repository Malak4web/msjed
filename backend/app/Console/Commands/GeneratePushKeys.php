<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('push:keys')]
#[Description('يولّد مفاتيح VAPID لإشعارات Push — انسخها في .env')]
class GeneratePushKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('ضيف السطور دي في ملف .env:');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->warn('لو غيّرت المفاتيح، كل الأجهزة لازم تفعّل الإشعارات من جديد.');

        return self::SUCCESS;
    }
}
