<?php

namespace App\Services\Workflow;

use App\Models\Deadline;
use App\Models\DeadlineRule;
use App\Models\ExecutionRequest;
use App\Models\Judgment;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Calendar;

/**
 * المواعيد والعدادات. القاعدة الأساسية: لا يُفترض أن تاريخ النطق بداية كل مهلة،
 * ولا تُطبَّق قاعدة افتراضية إلا إذا كانت معتمدة (active) وسارية في تاريخ الواقعة.
 * عند عدم التأكد يُنشأ الموعد بحالة "يحتاج تحديدًا" ليُدخل المحامي الموعد المعتمد.
 */
class DeadlineService
{
    public function __construct(private Calendar $cal) {}

    public function syncJudgmentDeadlines(Judgment $j): void
    {
        $case = $j->legalCase;
        $settled = in_array($j->outcome, ['settled'], true);
        if ($settled) {
            return;
        }
        // 1) متابعة تقديرية لصدور الصك — مستقلة عن عداد الاعتراض
        if ($j->deed_status === 'awaiting' && $j->pronounced_on) {
            $this->upsert('judgment:'.$j->id.':deed_followup', [
                'client_id' => $case->client_id, 'legal_case_id' => $case->id, 'kind' => 'deed_followup', 'certainty' => 'estimated',
                'reason' => 'متابعة تقديرية لصدور صك الحكم',
                'start_on' => $j->pronounced_on->toDateString(), 'duration' => 10, 'day_type' => 'calendar',
                'end_on' => $this->cal->add($j->pronounced_on, 10)->toDateString(),
                'basis' => 'متابعة داخلية بعد 10 أيام تقويمية من النطق — ليست مهلة نظامية ويمكن تعديلها.',
                'source' => 'system',
            ], onlyIfMissing: true);
        } elseif ($j->deed_status === 'issued') {
            $this->closeKey('judgment:'.$j->id.':deed_followup');
        }

        // 2) موعد الاعتراض: لا يُحسب آليًا من تاريخ النطق — يحتاج مراجعة المحامي لنقطة البداية والمدة
        if (! in_array($j->objection_status, ['objected', 'waived', 'not_applicable'], true) && $j->finality_status !== 'final') {
            $rule = DeadlineRule::applicable('objection', $j->deed_date ?? $j->pronounced_on ?? today_local());
            $attrs = [
                'client_id' => $case->client_id, 'legal_case_id' => $case->id, 'kind' => 'objection',
                'reason' => 'موعد اعتراض على الحكم',
                'source' => $rule ? 'rule' : 'system',
            ];
            if ($rule && $rule->start_event === 'deed_date' && $j->deed_date) {
                $attrs += ['certainty' => 'needs_review', 'rule_id' => $rule->id, 'start_on' => $j->deed_date->toDateString(),
                    'duration' => $rule->duration, 'day_type' => $rule->day_type,
                    'end_on' => $this->cal->add($j->deed_date, (int) $rule->duration, $rule->day_type)->toDateString(),
                    'basis' => "قاعدة {$rule->name} (إصدار {$rule->version}) — المصدر: {$rule->source_reference}. يحتاج مراجعة قبل الاعتماد."];
            } else {
                $attrs += ['certainty' => 'needs_determination', 'basis' => 'نقطة البداية والمدة غير مؤكدة — أدخل الموعد المعتمد بعد مراجعة الصك وطريقة التبليغ.'];
            }
            $this->upsert('judgment:'.$j->id.':objection', $attrs, onlyIfMissing: true);
        } else {
            $this->closeKey('judgment:'.$j->id.':objection');
        }
    }

    /**
     * مهلة التنفيذ (مثل خمسة أيام عمل) تُربط بالإبلاغ أو الإعلان الموثق فقط، ولا تعمل إلا بقاعدة معتمدة سارية.
     */
    public function syncExecutionCompliance(ExecutionRequest $e): void
    {
        $key = 'execution:'.$e->id.':compliance';
        $start = $e->notified_on;
        $base = ['client_id' => $e->client_id, 'legal_case_id' => $e->legal_case_id, 'execution_request_id' => $e->id, 'kind' => 'execution_compliance',
            'reason' => 'مهلة المنفذ ضده بعد الإبلاغ/الإعلان بأمر التنفيذ'];
        if (! $start) {
            if ($e->order_issued_on) {
                $this->upsert($key, $base + ['certainty' => 'needs_determination', 'source' => 'system',
                    'basis' => 'لم يُسجّل تاريخ الإبلاغ أو الإعلان — لا تبدأ المهلة قبل توثيقه.'], onlyIfMissing: true);
            }

            return;
        }
        $rule = DeadlineRule::applicable('execution_compliance', $start);
        $existing = Deadline::where('unique_key', $key)->first();
        if ($existing && $existing->certainty === 'confirmed') {
            return; // موعد اعتمده المحامي لا يُستبدل آليًا
        }
        if ($rule) {
            $end = $this->cal->add($start, (int) $rule->duration, $rule->day_type)->toDateString();
            $this->upsert($key, $base + ['certainty' => 'needs_review', 'rule_id' => $rule->id, 'source' => 'rule',
                'start_on' => $start->toDateString(), 'duration' => $rule->duration, 'day_type' => $rule->day_type, 'end_on' => $end,
                'basis' => "{$rule->duration} ".($rule->day_type === 'business' ? 'أيام عمل' : 'أيام')." من تاريخ ".($e->notification_method === 'publication' ? 'الإعلان' : 'الإبلاغ')
                    ." ({$start->toDateString()}) — قاعدة {$rule->name} إصدار {$rule->version}. المصدر: {$rule->source_reference}"]);
        } else {
            $this->upsert($key, $base + ['certainty' => 'needs_determination', 'source' => 'system', 'start_on' => $start->toDateString(),
                'end_on' => null, 'duration' => null, 'day_type' => null, 'rule_id' => null,
                'basis' => 'لا توجد قاعدة معتمدة سارية لهذا التاريخ أو لم يتأكد انطباقها — أدخل الموعد المعتمد يدويًا.']);
        }
    }

    private function upsert(string $key, array $attrs, bool $onlyIfMissing = false): Deadline
    {
        $d = Deadline::where('unique_key', $key)->first();
        if ($d && ($onlyIfMissing || $d->certainty === 'confirmed')) {
            return $d;
        }
        $d ??= new Deadline(['unique_key' => $key]);
        $d->fill($attrs + ['status' => 'open']);
        $d->updated_by = auth()->id();
        $d->save();

        return $d;
    }

    private function closeKey(string $key): void
    {
        Deadline::where('unique_key', $key)->where('status', '!=', 'closed')->update(['status' => 'closed', 'updated_at' => now()]);
    }

    public function closeFor(LegalCase $case, array $kinds): void
    {
        $case->deadlines()->whereIn('kind', $kinds)->where('status', '!=', 'closed')->update(['status' => 'closed', 'updated_at' => now()]);
    }

    /** تعديل المحامي: تاريخ نهاية مباشر، أو مدة مع عرض أساس الحساب */
    public function manualUpdate(Deadline $d, array $data): void
    {
        $old = $d->end_on?->toDateString();
        if (! empty($data['end_on'])) {
            $d->end_on = $data['end_on'];
            $d->duration = null;
            $d->basis = $data['basis'] ?? 'تاريخ نهاية أدخله المحامي';
        } elseif (! empty($data['start_on']) && isset($data['duration'])) {
            $d->start_on = $data['start_on'];
            $d->duration = (int) $data['duration'];
            $d->day_type = $data['day_type'] ?? 'calendar';
            $d->end_on = $this->cal->add($data['start_on'], (int) $data['duration'], $d->day_type)->toDateString();
            $d->basis = ($data['basis'] ?? null) ?: "{$d->duration} ".($d->day_type === 'business' ? 'يوم عمل' : 'يومًا تقويميًا')." من {$data['start_on']}";
        }
        foreach (['certainty', 'reason', 'extension_reason'] as $f) {
            if (array_key_exists($f, $data) && $data[$f] !== null) {
                $d->{$f} = $data[$f];
            }
        }
        if ($d->status === 'alerted' && $d->end_on && $d->end_on->gte(today_local())) {
            $d->status = 'open';
            $d->alerted_at = null;
        }
        $d->source = $d->source === 'manual' ? 'manual' : $d->source.'+manual';
        $d->updated_by = auth()->id();
        $d->save();
        Audit::log('deadline.updated', $d, ['old_end' => $old, 'new_end' => $d->end_on?->toDateString(), 'certainty' => $d->certainty]);
    }

    /** يُشغَّل يوميًا: انتهاء أي عداد يرفع تنبيه متابعة (لا يُنشئ قرارًا ولا يثبت صدوره) */
    public function raiseExpiredAlerts(): int
    {
        $today = today_local()->toDateString();
        $rows = Deadline::where('status', 'open')->whereNotNull('end_on')->whereDate('end_on', '<', $today)->get();
        foreach ($rows as $d) {
            $d->forceFill(['status' => 'alerted', 'alerted_at' => now()])->save();
            Audit::log('deadline.expired_alert', $d, ['kind' => $d->kind], null);
        }

        return $rows->count();
    }
}
