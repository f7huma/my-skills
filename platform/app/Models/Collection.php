<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عملية مالية مستقلة. كل دفعة صف واحد بمفتاح فريد (entry_key)، ويُربط بالقضية والتنفيذ والصلح في آن،
 * فلا تُحتسب الدفعة مرتين عند انتقال الملف من الصلح إلى التنفيذ.
 */
class Collection extends Model
{
    protected $guarded = ['id'];

    public const KINDS = ['claimed' => 'المبلغ المطلوب', 'agreed' => 'المتفق عليه', 'seized' => 'المحجوز', 'received' => 'المقبوض فعليًا'];

    public const SOURCES = ['reconciliation' => 'صلح', 'execution' => 'تنفيذ', 'other' => 'أخرى'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_on' => 'date', 'visible_to_client' => 'boolean'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * مجاميع لكل نوع ولكل عملة على حدة بحساب عشري دقيق (يُجمع في PostgreSQL على numeric).
     *
     * @return array<string, array<string, string>> [kind => [currency => "123.45"]]
     */
    public static function totals($query): array
    {
        $rows = (clone $query)->selectRaw('kind, currency, sum(amount)::text as total')->groupBy('kind', 'currency')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->kind][$r->currency] = Money::normalize($r->total);
        }

        return $out;
    }
}
