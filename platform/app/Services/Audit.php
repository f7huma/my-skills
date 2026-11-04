<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل التدقيق: يوثق الحدث والمنفّذ والسجل المعني فقط.
 * لا يُخزّن محتوى المستندات أو أسماء الملفات أو الأسرار — meta يقبل مفاتيح وصفية قصيرة.
 */
class Audit
{
    private const FORBIDDEN_KEYS = ['password', 'token', 'secret', 'body', 'body_html', 'content', 'original_name', 'recovery_codes', 'file'];

    public static function log(string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): void
    {
        foreach (array_keys($meta) as $k) {
            if (in_array($k, self::FORBIDDEN_KEYS, true)) {
                unset($meta[$k]);
            }
        }
        $meta = array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 200) : $v, $meta);

        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
