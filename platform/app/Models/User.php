<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const ROLE_LAWYER = 'lawyer';

    public const ROLE_CLIENT = 'client';

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    public function isLawyer(): bool
    {
        return $this->role === self::ROLE_LAWYER && $this->is_active;
    }

    public function isClientUser(): bool
    {
        return $this->role === self::ROLE_CLIENT && $this->is_active;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_memberships')->withPivot(['can_view', 'can_submit_claims', 'revoked_at']);
    }

    /**
     * معرّفات العملاء التي يملك المستخدم صلاحية عليها — تُستمد من العضويات السارية على الخادم في كل طلب.
     *
     * @param  'view'|'claims'  $ability
     * @return array<int>
     */
    public function clientIdsFor(string $ability = 'view'): array
    {
        if (! $this->isClientUser()) {
            return [];
        }
        $column = $ability === 'claims' ? 'can_submit_claims' : 'can_view';

        return $this->memberships()
            ->whereNull('revoked_at')
            ->where($column, true)
            ->whereHas('client', fn ($q) => $q->whereNull('archived_at'))
            ->pluck('client_id')
            ->all();
    }

    public function canAccessClient(int $clientId, string $ability = 'view'): bool
    {
        return in_array($clientId, $this->clientIdsFor($ability), true);
    }
}
