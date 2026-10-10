<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * العمليات المالية لا تُحذف — تُلغى بقيد عكسي وتفضل ظاهرة.
 */
trait Reversible
{
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function scopeNotReversed(Builder $query): Builder
    {
        return $query->whereNull($this->getTable().'.reversed_at');
    }
}
