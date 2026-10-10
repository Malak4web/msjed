<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\Reversible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExpenseRequest extends Model
{
    use Audited, Reversible;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const EXECUTED = 'executed';
    public const REJECTED = 'rejected';
    public const RETURNED = 'returned';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'بانتظار موافقة الإمام',
        self::APPROVED => 'معتمد – بانتظار التنفيذ',
        self::EXECUTED => 'تم التنفيذ',
        self::REJECTED => 'مرفوض',
        self::RETURNED => 'مُرجَع للتعديل',
        self::CANCELLED => 'مُلغى',
    ];

    protected $fillable = [
        'number', 'custodian_id', 'expense_category_id', 'type', 'amount', 'description', 'status', 'requested_by',
        'approved_by', 'approved_at', 'auto_approved', 'auto_approval_reason', 'rejection_reason', 'return_reason',
        'cancel_reason', 'executed_at', 'executed_date', 'executed_by', 'actual_amount', 'recurring_expense_period_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'executed_date' => 'date',
            'reversed_at' => 'datetime',
            'auto_approved' => 'boolean',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Custodian::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function recurringPeriod(): BelongsTo
    {
        return $this->belongsTo(RecurringExpensePeriod::class, 'recurring_expense_period_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExpenseRequestEvent::class)->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
