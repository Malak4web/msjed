<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use Audited;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_VARIABLE = 'variable';

    protected $fillable = ['name', 'type', 'icon', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
