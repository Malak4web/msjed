<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PeriodLock;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PeriodLockService
{
    public function isLocked(CarbonInterface|string $date): bool
    {
        $period = Carbon::parse($date)->startOfMonth()->toDateString();

        return PeriodLock::whereDate('period', $period)->exists();
    }

    public function assertOpen(CarbonInterface|string $date): void
    {
        if ($this->isLocked($date)) {
            $month = Carbon::parse($date)->locale('ar')->translatedFormat('F Y');
            throw new BusinessRuleException("شهر {$month} مقفول، مينفعش تسجّل أو تعدّل حاجة بتاريخ جوّاه.");
        }
    }

    public function lock(string $period, User $user): PeriodLock
    {
        $date = Carbon::parse($period)->startOfMonth();

        if ($date->isSameMonth(now()) || $date->isFuture()) {
            throw new BusinessRuleException('مينفعش تقفل الشهر الحالي أو شهر لسه مجاش.');
        }

        return PeriodLock::whereDate('period', $date->toDateString())->first()
            ?? PeriodLock::create(['period' => $date->toDateString(), 'locked_by' => $user->id]);
    }

    public function unlock(string $period): void
    {
        $lock = PeriodLock::whereDate('period', Carbon::parse($period)->startOfMonth()->toDateString())->first();
        $lock?->delete();

        activity('audit')->event('unlocked')->withProperties(['period' => $period])->log('فتح شهر مقفول');
    }
}
