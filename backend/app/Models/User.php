<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasApiTokens, HasFactory, HasRoles, Notifiable;

    public const ROLE_IMAM = 'imam';
    public const ROLE_TREASURER = 'treasurer';
    public const ROLE_ACCOUNTANT = 'accountant';

    public const ROLE_LABELS = [
        self::ROLE_IMAM => 'إمام المسجد',
        self::ROLE_TREASURER => 'أمين الصندوق',
        self::ROLE_ACCOUNTANT => 'المحاسب',
    ];

    protected $fillable = ['name', 'phone', 'email', 'password', 'is_active', 'custodian_id', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Custodian::class);
    }

    public function isImam(): bool
    {
        return $this->hasRole(self::ROLE_IMAM);
    }

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function roleLabel(): ?string
    {
        return self::ROLE_LABELS[$this->roleName()] ?? null;
    }
}
