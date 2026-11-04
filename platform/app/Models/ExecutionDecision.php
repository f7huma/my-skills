<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** قرار مسجّل من واقع المستند أو ما ظهر فعليًا في ناجز — لا يُنشأ آليًا بانتهاء المهلة */
class ExecutionDecision extends Model
{
    protected $guarded = ['id'];

    public const LABELS = ['قرار 34', 'قرار 46'];

    protected function casts(): array
    {
        return ['decided_on' => 'date'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
