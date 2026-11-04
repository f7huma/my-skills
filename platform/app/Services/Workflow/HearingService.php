<?php

namespace App\Services\Workflow;

use App\Models\Hearing;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Timeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HearingService
{
    public function __construct(private TaskService $tasks) {}

    public static function parseLocal(?string $local): ?Carbon
    {
        return $local ? Carbon::parse($local, config('office.display_timezone'))->utc() : null;
    }

    public function schedule(LegalCase $case, array $data): Hearing
    {
        $h = $case->hearings()->create([
            'kind' => $data['kind'],
            'reconciliation_request_id' => $data['reconciliation_request_id'] ?? null,
            'court_filing_id' => $data['court_filing_id'] ?? null,
            'scheduled_at' => self::parseLocal($data['scheduled_at']),
            'authority' => $data['authority'] ?? null,
            'meeting_url' => $data['meeting_url'] ?? null,
            'status' => 'scheduled',
        ]);
        Timeline::record($case, 'hearing.scheduled', 'جدولة '.Hearing::KINDS[$h->kind], $this->fmt($h), $h, true);
        Audit::log('hearing.scheduled', $h);

        return $h;
    }

    /** تعديل موعد الجلسة نفسها (تصحيح) مع إعادة حساب المهام المرتبطة وتسجيل السبب */
    public function reschedule(Hearing $h, string $newLocal, string $reason, int $version): Hearing
    {
        return DB::transaction(function () use ($h, $newLocal, $reason, $version) {
            $old = $this->fmt($h);
            $h->scheduled_at = self::parseLocal($newLocal);
            $h->change_reason = $reason;
            $h->saveWithVersion($version);
            $this->tasks->reanchor($h, $h, $reason);
            Timeline::record($h->legalCase, 'hearing.rescheduled', 'تعديل موعد '.Hearing::KINDS[$h->kind], "من {$old} إلى {$this->fmt($h)} — السبب: {$reason}", $h, true);
            Audit::log('hearing.rescheduled', $h);

            return $h;
        });
    }

    /** تأجيل: تُحفظ الجلسة الأصلية كمؤجلة وتُنشأ جلسة جديدة، وتنتقل المهام المرتبطة إليها */
    public function postpone(Hearing $h, ?string $newLocal, string $reason, int $version): ?Hearing
    {
        return DB::transaction(function () use ($h, $newLocal, $reason, $version) {
            $case = $h->legalCase;
            $next = null;
            if ($newLocal) {
                $next = $case->hearings()->create([
                    'kind' => $h->kind, 'reconciliation_request_id' => $h->reconciliation_request_id, 'court_filing_id' => $h->court_filing_id,
                    'scheduled_at' => self::parseLocal($newLocal), 'authority' => $h->authority, 'meeting_url' => $h->meeting_url, 'status' => 'scheduled',
                ]);
            }
            $h->status = 'postponed';
            $h->change_reason = $reason;
            $h->next_hearing_id = $next?->id;
            $h->saveWithVersion($version);
            $this->tasks->reanchor($h, $next, 'تأجيل الجلسة: '.$reason);
            Timeline::record($case, 'hearing.postponed', 'تأجيل '.Hearing::KINDS[$h->kind],
                'الموعد السابق: '.$this->fmt($h).($next ? ' — الموعد الجديد: '.$this->fmt($next) : ' — لم يُحدد موعد جديد').' — السبب: '.$reason, $h, true);
            Audit::log('hearing.postponed', $h, ['next' => $next?->id]);

            return $next;
        });
    }

    public function cancel(Hearing $h, string $reason, int $version): void
    {
        DB::transaction(function () use ($h, $reason, $version) {
            $h->status = 'cancelled';
            $h->change_reason = $reason;
            $h->saveWithVersion($version);
            $this->tasks->reanchor($h, null, 'إلغاء الجلسة: '.$reason);
            Timeline::record($h->legalCase, 'hearing.cancelled', 'إلغاء '.Hearing::KINDS[$h->kind], 'الموعد: '.$this->fmt($h).' — السبب: '.$reason, $h, true);
            Audit::log('hearing.cancelled', $h);
        });
    }

    /**
     * إكمال الجلسة: الحضور والنتيجة والتوجيهات والضبط، مع إضافة عدة مهام من الشاشة نفسها.
     *
     * @param  array<int, array<string,mixed>>  $newTasks
     */
    public function complete(Hearing $h, array $data, array $newTasks, int $version): void
    {
        DB::transaction(function () use ($h, $data, $newTasks, $version) {
            $h->fill([
                'status' => 'completed',
                'attendance' => $data['attendance'] ?? null,
                'result' => $data['result'] ?? null,
                'directives' => $data['directives'] ?? null,
                'completed_at' => now(),
            ]);
            if (! empty($data['minutes_document_id'])) {
                $h->minutes_document_id = $data['minutes_document_id'];
            }
            $h->saveWithVersion($version);
            $case = $h->legalCase;
            $next = null;
            if (! empty($data['next_hearing_at'])) {
                $next = $this->schedule($case, ['kind' => $h->kind, 'scheduled_at' => $data['next_hearing_at'], 'authority' => $h->authority,
                    'reconciliation_request_id' => $h->reconciliation_request_id, 'court_filing_id' => $h->court_filing_id, 'meeting_url' => $h->meeting_url]);
                $h->forceFill(['next_hearing_id' => $next->id])->save();
            }
            foreach ($newTasks as $t) {
                if (($t['due_mode'] ?? '') === 'next_hearing' && $next && empty($t['anchor_hearing_id'])) {
                    $t['anchor_hearing_id'] = $next->id;
                }
                $this->tasks->create($t, $case, $h);
            }
            Timeline::record($case, 'hearing.completed', 'انعقاد '.Hearing::KINDS[$h->kind], $data['result'] ?? null, $h, false);
            Audit::log('hearing.completed', $h, ['tasks' => count($newTasks)]);
        });
    }

    public function fmt(Hearing $h): string
    {
        return $h->scheduled_at?->copy()->setTimezone(config('office.display_timezone'))->format('Y-m-d H:i') ?? '—';
    }
}
