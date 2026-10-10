<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * سجل تدقيق تلقائي لأي إنشاء/تعديل مع القيم قبل وبعد.
 */
trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'updated_at', 'created_at', 'balance_cache', 'last_login_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('audit');
    }
}
