<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringExpense extends Model
{
    use Audited;

    protected $fillable = [
        'name', 'expense_category_id', 'amount', 'due_day', 'responsible_custodian_id', 'pre_approved', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'pre_approved' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Custodian::class, 'responsible_custodian_id');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(RecurringExpensePeriod::class);
    }
}
