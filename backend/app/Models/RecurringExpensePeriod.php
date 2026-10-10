<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpensePeriod extends Model
{
    public const DUE = 'due';
    public const OVERDUE = 'overdue';
    public const PAID = 'paid';

    protected $fillable = ['recurring_expense_id', 'period', 'due_date', 'status', 'expense_request_id'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'due_date' => 'date',
        ];
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class);
    }
}
