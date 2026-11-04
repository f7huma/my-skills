<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMembership extends Model
{
    protected $fillable = ['client_id', 'user_id', 'can_view', 'can_submit_claims', 'revoked_at', 'granted_by'];

    protected function casts(): array
    {
        return ['can_view' => 'boolean', 'can_submit_claims' => 'boolean', 'revoked_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
