<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\ClientReport;
use App\Models\Collection;
use App\Models\ExecutionRequest;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Services\Calendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * التقارير الدورية للعميل.
 * - الإعداد: يجمع ما يخص العميل خلال الفترة مما يصح أن يراه (أحداث السجل المسموح بها للعميل، الجلسات المنعقدة
 *   دون نتائجها الداخلية، طلبات الإجراء دون ملاحظاتها، المبالغ المقبوضة الظاهرة للعميل) — بلا ملاحظات داخلية.
 * - العرض: بنية واحدة تستخدمها الشاشة التفاعلية وملف PDF.
 */
class ClientReportService
{
    /** أنواع أحداث داخلية يُكتفى بعنوانها دون التفاصيل */
    private const TITLE_ONLY = ['execution.action', 'execution.action_status', 'hearing.completed'];

    public function __construct(private Calendar $cal) {}

    /** يحذف رموز مراجع المكتب الداخلية (CS-2026-0001 وأمثالها) من النص المعروض للعميل */
    public static function clean(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = preg_replace('/\s*(?:—|-|:)?\s*\b(?:CS|EX|CL|CLM|POA)-\d{4}-\d{3,}\b/u', '', $v);
        $v = trim(preg_replace('/\s{2,}/u', ' ', $v), " \t\n—-·:");

        return $v === '' ? null : $v;
    }

    /** حدود الفترة بالتقويم الميلادي: الأسبوع يبدأ الأحد، والشهر والسنة كاملان */
    public function range(string $period, string $anchor): array
    {
        $d = CarbonImmutable::parse($anchor, config('office.display_timezone'))->startOfDay();

        return match ($period) {
            'weekly' => [$d->subDays($d->dayOfWeek), $d->subDays($d->dayOfWeek)->addDays(6)],
            'monthly' => [$d->startOfMonth(), $d->endOfMonth()->startOfDay()],
            'yearly' => [$d->startOfYear(), $d->endOfYear()->startOfDay()],
        };
    }

    public function title(string $period, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $fmt = fn (CarbonImmutable $x, string $p) => (new \IntlDateFormatter('ar_SA@calendar=gregorian;numbers=latn', 0, 0, config('office.display_timezone'), null, $p))->format($x);

        return match ($period) {
            'weekly' => 'التقرير الأسبوعي — '.$fmt($start, 'd MMMM').' إلى '.$fmt($end, 'd MMMM y'),
            'monthly' => 'التقرير الشهري — '.$fmt($start, 'MMMM y'),
            'yearly' => 'التقرير السنوي — '.$start->year,
        };
    }

    public function generate(Client $client, string $period, string $anchor, User $by): ClientReport
    {
        [$start, $end] = $this->range($period, $anchor);

        return DB::transaction(function () use ($client, $period, $start, $end, $by) {
            $report = ClientReport::create([
                'client_id' => $client->id, 'period' => $period, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'title' => $this->title($period, $start, $end), 'matters' => [], 'status' => 'draft', 'created_by' => $by->id,
            ]);
            $this->fill($report);

            return $report;
        });
    }

    /** يعيد بناء الإجراءات المولَّدة ولقطة الملفات، ويُبقي ما أضافه المحامي يدويًا */
    public function fill(ClientReport $report): void
    {
        $tz = config('office.display_timezone');
        $from = CarbonImmutable::parse($report->period_start->toDateString(), $tz)->startOfDay();
        $to = CarbonImmutable::parse($report->period_end->toDateString(), $tz)->endOfDay();
        $cid = $report->client_id;
        $hidden = collect($report->matters ?? [])->where('hidden', true)->pluck('key')->all();

        $items = [];
        $touched = [];
        $events = TimelineEvent::where('client_id', $cid)->whereBetween('occurred_at', [$from->utc(), $to->utc()])
            ->where(fn ($q) => $q->where('client_visible', true)->orWhereIn('type', self::TITLE_ONLY))
            ->orderBy('occurred_at')->get();
        foreach ($events as $e) {
            $key = $e->execution_request_id ? 'execution:'.$e->execution_request_id : ($e->legal_case_id ? 'case:'.$e->legal_case_id : 'general');
            $items[] = ['matter_key' => $key, 'occurred_on' => $e->occurred_at->copy()->setTimezone($tz)->toDateString(),
                'title' => self::clean($e->title) ?? $e->title, 'details' => in_array($e->type, self::TITLE_ONLY, true) ? null : self::clean($e->details)];
            $touched[$key] = true;
        }
        // الجلسات التي انعقدت خلال الفترة (بلا نتائجها الداخلية)
        $hearings = Hearing::with('legalCase:id,client_id')->whereHas('legalCase', fn ($q) => $q->where('client_id', $cid))
            ->where('status', 'completed')->whereBetween('scheduled_at', [$from->utc(), $to->utc()])->get();
        foreach ($hearings as $h) {
            $key = 'case:'.$h->legal_case_id;
            $on = $h->scheduled_at->copy()->setTimezone($tz);
            $exists = collect($items)->contains(fn ($i) => $i['matter_key'] === $key && $i['occurred_on'] === $on->toDateString() && str_contains($i['title'], 'جلسة'));
            if (! $exists) {
                $items[] = ['matter_key' => $key, 'occurred_on' => $on->toDateString(), 'title' => 'حضور '.Hearing::KINDS[$h->kind].($h->authority ? ' — '.$h->authority : ''), 'details' => null];
            }
            $touched[$key] = true;
        }
        // المبالغ المقبوضة الظاهرة للعميل
        $received = Collection::where('client_id', $cid)->where('kind', 'received')->where('visible_to_client', true)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])->get();
        foreach ($received as $c) {
            $key = $c->execution_request_id ? 'execution:'.$c->execution_request_id : ($c->legal_case_id ? 'case:'.$c->legal_case_id : 'general');
            $items[] = ['matter_key' => $key, 'occurred_on' => $c->occurred_on->toDateString(),
                'title' => 'تحصيل مبلغ '.number_format((float) $c->amount, 2).' '.$c->currency, 'details' => $c->description];
            $touched[$key] = true;
        }

        // لقطة الملفات: كل الملفات السارية، وأي ملف وقع عليه إجراء خلال الفترة
        $cases = LegalCase::with('filings')->where('client_id', $cid)->whereNull('archived_at')->where('work_status', '!=', 'draft')->get()
            ->filter(fn ($c) => in_array($c->work_status, LegalCase::ACTIVE_STATUSES, true) || isset($touched['case:'.$c->id]));
        $execs = ExecutionRequest::where('client_id', $cid)->where('status', '!=', 'draft')->get()
            ->filter(fn ($e) => ! in_array($e->status, ['completed', 'closed', 'rejected'], true) || isset($touched['execution:'.$e->id]));
        $matters = [];
        foreach ($cases as $c) {
            $filing = $c->filings->whereNotNull('case_number')->sortByDesc('registered_on')->first();
            $matters[] = ['key' => 'case:'.$c->id, 'kind' => 'case', 'id' => $c->id, 'title' => $c->title, 'number' => $filing?->case_number,
                'court' => $filing?->court, 'stage' => $c->stage, 'stageLabel' => LegalCase::STAGES[$c->stage] ?? null,
                'status' => LegalCase::WORK_STATUSES[$c->work_status] ?? null, 'statusKey' => $c->work_status, 'hidden' => in_array('case:'.$c->id, $hidden, true)];
        }
        foreach ($execs as $e) {
            $matters[] = ['key' => 'execution:'.$e->id, 'kind' => 'execution', 'id' => $e->id,
                'title' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type].' — '.($e->instrument_type === 'other' ? ($e->instrument_label ?: 'أخرى') : ExecutionRequest::INSTRUMENT_TYPES[$e->instrument_type]),
                'number' => $e->request_number, 'court' => null, 'stage' => null, 'stageLabel' => null,
                'status' => ExecutionRequest::STATUSES[$e->status] ?? $e->status, 'statusKey' => $e->status, 'hidden' => in_array('execution:'.$e->id, $hidden, true)];
        }
        // ملف ظهر في الإجراءات لكنه خارج اللقطة (مؤرشف مثلًا) يُنقل إلى «عام» حتى لا يضيع
        $keys = collect($matters)->pluck('key')->push('general')->all();
        foreach ($items as &$i) {
            if (! in_array($i['matter_key'], $keys, true)) {
                $i['matter_key'] = 'general';
            }
        }
        unset($i);

        $report->items()->where('source', 'auto')->delete();
        foreach ($items as $i) {
            $report->items()->create($i + ['source' => 'auto']);
        }
        $report->forceFill(['matters' => $matters])->save();
    }

    /** بنية العرض المشتركة بين الشاشة وPDF */
    public function present(ClientReport $r, bool $forClient = false): array
    {
        $r->loadMissing('client:id,name', 'items');
        $byKey = $r->items->groupBy('matter_key');
        $item = fn ($i) => ['id' => $i->id, 'on' => $i->occurred_on->toDateString(), 'hijri' => $this->cal->hijri($i->occurred_on),
            'title' => $i->title, 'details' => $i->details, 'source' => $i->source, 'matter' => $i->matter_key];
        $matters = collect($r->matters ?? [])
            ->when($forClient, fn ($m) => $m->reject(fn ($x) => $x['hidden'] ?? false))
            ->map(fn ($m) => $m + ['items' => ($byKey[$m['key']] ?? collect())->map($item)->values()->all()])->values()->all();
        $general = ($byKey['general'] ?? collect())->map($item)->values()->all();
        $visibleItems = collect($matters)->sum(fn ($m) => count($m['items'])) + count($general);

        return [
            'id' => $r->id, 'title' => $r->title, 'period' => $r->period, 'periodLabel' => ClientReport::PERIODS[$r->period],
            'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(),
            'startHijri' => $this->cal->hijri($r->period_start), 'endHijri' => $this->cal->hijri($r->period_end),
            'client' => ['id' => $r->client_id, 'name' => $r->client->name],
            'intro' => $r->intro, 'nextSteps' => $r->next_steps, 'status' => $r->status, 'statusLabel' => ClientReport::STATUSES[$r->status],
            'publishedAt' => $r->published_at?->toIso8601String(), 'lockVersion' => $r->lock_version,
            'matters' => $matters, 'general' => $general,
            'stats' => [
                'cases' => collect($matters)->where('kind', 'case')->count(),
                'executions' => collect($matters)->where('kind', 'execution')->count(),
                'actions' => $visibleItems,
                'hearings' => collect($matters)->flatMap(fn ($m) => $m['items'])->filter(fn ($i) => str_contains($i['title'], 'جلسة'))->count(),
            ],
        ];
    }
}
