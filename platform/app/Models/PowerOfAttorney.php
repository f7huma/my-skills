<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PowerOfAttorney extends Model
{
    protected $table = 'powers_of_attorney';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'expires_on' => 'date', 'revoked_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** active | expiring | expired | revoked */
    public function effectiveStatus(?int $warningDays = null): string
    {
        if ($this->status === 'revoked') {
            return 'revoked';
        }
        if (! $this->expires_on) {
            return 'active';
        }
        $today = today_local();
        if ($this->expires_on->lt($today)) {
            return 'expired';
        }
        $warningDays ??= Setting::get('poa_expiry_warning_days', config('office.poa_expiry_warning_days'));

        return $this->expires_on->lte($today->copy()->addDays((int) $warningDays)) ? 'expiring' : 'active';
    }
}
