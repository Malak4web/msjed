<?php

namespace App\Console\Commands;

use App\Services\RecurringExpenseService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recurring:run')]
#[Description('يولّد مستحقات المصروفات الثابتة للشهر الحالي ويعلّم المتأخر')]
class RunRecurringExpenses extends Command
{
    public function handle(RecurringExpenseService $recurring): int
    {
        $created = $recurring->generateForMonth();
        $overdue = $recurring->markOverdue();

        $this->info("اتولّد {$created} مستحق جديد، واتعلّم {$overdue} كمتأخر.");

        return self::SUCCESS;
    }
}
