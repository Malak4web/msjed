<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\Reversible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use Audited, Reversible;

    protected $fillable = ['number', 'date', 'from_custodian_id', 'to_custodian_id', 'amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Custodian::class, 'from_custodian_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Custodian::class, 'to_custodian_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
