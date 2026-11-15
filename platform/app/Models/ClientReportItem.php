<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** إجراء واحد في التقرير — مولَّد من سجل الأعمال أو مضاف يدويًا من المحامي */
class ClientReportItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'fields' => 'array', 'reflected_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ClientReport::class, 'client_report_id');
    }
}
