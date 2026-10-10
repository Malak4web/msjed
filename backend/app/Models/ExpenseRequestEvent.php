<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseRequestEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'created' => 'تم إنشاء الطلب',
        'approved' => 'وافق الإمام على الطلب',
        'auto_approved' => 'اتوافق تلقائيًا',
        'rejected' => 'تم رفض الطلب',
        'returned' => 'اترجع للتعديل',
        'resubmitted' => 'اتعدل واتبعت تاني للإمام',
        'executed' => 'تم التنفيذ ورفع الفاتورة',
        'cancelled' => 'تم إلغاء الطلب',
        'reversed' => 'اتلغى بقيد عكسي',
    ];

    protected $fillable = ['expense_request_id', 'action', 'user_id', 'note', 'meta', 'created_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class, 'expense_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
