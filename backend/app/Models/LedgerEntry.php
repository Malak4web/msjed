<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    public const INCOME = 'income';
    public const TRANSFER_IN = 'transfer_in';
    public const TRANSFER_OUT = 'transfer_out';
    public const EXPENSE = 'expense';
    public const REVERSAL = 'reversal';

    public const TYPE_LABELS = [
        self::INCOME => 'إيراد',
        self::TRANSFER_IN => 'استلام من شخص',
        self::TRANSFER_OUT => 'تسليم لشخص',
        self::EXPENSE => 'مصروف',
        self::REVERSAL => 'قيد عكسي',
    ];

    protected $fillable = ['custodian_id', 'type', 'amount', 'date', 'description', 'related_type', 'related_id', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Custodian::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
