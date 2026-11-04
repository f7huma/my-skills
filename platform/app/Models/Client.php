<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $guarded = ['id', 'reference'];

    public const TYPES = ['individual' => 'فرد', 'organization' => 'منشأة'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime', 'is_synthetic' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $c) {
            $c->reference ??= References::next('CL', 'clients');
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }

    public function powersOfAttorney(): HasMany
    {
        return $this->hasMany(PowerOfAttorney::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class);
    }

    public function executionRequests(): HasMany
    {
        return $this->hasMany(ExecutionRequest::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /** إحصاءات القضايا محسوبة آليًا؛ الموقوفة والمشطوبة لا تُعدّ مكتملة */
    public function caseCounts(): array
    {
        $rows = $this->cases()->whereNull('archived_at')->selectRaw('work_status, count(*) as c')->groupBy('work_status')->pluck('c', 'work_status');
        $get = fn (array $s) => (int) collect($s)->sum(fn ($k) => $rows[$k] ?? 0);

        return [
            'total' => (int) $rows->sum(),
            'active' => $get(LegalCase::ACTIVE_STATUSES),
            'completed' => $get(['completed']),
            'resumable' => $get(LegalCase::RESUMABLE_STATUSES),
        ];
    }
}
