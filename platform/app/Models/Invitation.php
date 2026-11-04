<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['email', 'name', 'role', 'purpose', 'client_id', 'can_view', 'can_submit_claims', 'token_hash', 'expires_at', 'invited_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime',
            'can_view' => 'boolean', 'can_submit_claims' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
