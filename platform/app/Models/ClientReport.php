<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** تقرير دوري للعميل (أسبوعي/شهري/سنوي). يظهر للعميل بعد النشر فقط */
class ClientReport extends Model
{
    protected $guarded = ['id', 'lock_version'];

    public const PERIODS = ['weekly' => 'أسبوعي', 'monthly' => 'شهري', 'yearly' => 'سنوي'];

    public const STATUSES = ['draft' => 'مسودة مفتوحة', 'closed' => 'مكتمل — بانتظار النشر', 'published' => 'منشور للعميل'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'matters' => 'array', 'published_at' => 'datetime', 'closed_at' => 'datetime', 'dismissed_refs' => 'array', 'collected' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientReportItem::class)->orderBy('occurred_on')->orderBy('id');
    }

    /** المسودة المفتوحة وحدها تلتقط الإجراءات الجديدة تلقائيًا */
    public function isOpen(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
