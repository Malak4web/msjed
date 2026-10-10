<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Custodian extends Model
{
    use Audited;

    protected $fillable = ['name', 'title', 'phone', 'photo_path', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'balance_cache' => 'decimal:2',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function expenseRequests(): HasMany
    {
        return $this->hasMany(ExpenseRequest::class);
    }
}
