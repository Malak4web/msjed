<?php

use Illuminate\Support\Facades\Schedule;

// المصروفات الثابتة: توليد مستحقات الشهر + تعليم المتأخر (آمن يتكرر)
Schedule::command('recurring:run')->dailyAt('00:15')->withoutOverlapping();

// النسخ الاحتياطي اليومي لقاعدة البيانات والمرفقات + مسح النسخ القديمة
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
