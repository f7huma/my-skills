<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_path', 'key_id', 'sha256'];

    public const CATEGORIES = [
        'fee_contract' => 'عقد الأتعاب',
        'poa' => 'الوكالة',
        'identity' => 'الهوية',
        'commercial_registration' => 'السجل التجاري',
        'articles' => 'عقد التأسيس',
        'national_address' => 'العنوان الوطني',
        'invoice' => 'فاتورة',
        'statement' => 'كشف حساب',
        'promissory_note' => 'سند لأمر',
        'balance_confirmation' => 'مطابقة رصيد',
        'agreement' => 'اتفاقية',
        'reconciliation_report' => 'تقرير الصلح',
        'hearing_minutes' => 'ضبط الجلسة',
        'deed' => 'صك الحكم',
        'finality' => 'مستند النهائية',
        'memo_filed' => 'مذكرة مودعة',
        'execution' => 'مستند تنفيذ',
        'receipt' => 'إثبات قبض',
        'other' => 'أخرى',
    ];

    public const PARTIES = ['client' => 'موكل المكتب', 'opponent' => 'الطرف المطالب ضده', 'office' => 'المكتب', 'court' => 'الجهة القضائية'];

    protected function casts(): array
    {
        return [
            'original_name' => 'encrypted',
            'shared_with_client' => 'boolean',
            'shared_at' => 'datetime',
            'scanned_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isAvailable(): bool
    {
        return $this->scan_status === 'clean' && $this->deleted_at === null;
    }

    public function scopeAlive($q)
    {
        return $q->whereNull('deleted_at');
    }
}
