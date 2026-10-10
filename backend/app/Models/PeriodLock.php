<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodLock extends Model
{
    use Audited;

    protected $fillable = ['period', 'locked_by'];

    protected function casts(): array
    {
        return ['period' => 'date'];
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
