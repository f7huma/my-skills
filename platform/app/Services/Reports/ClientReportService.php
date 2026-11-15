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

    /** يحذف التواريخ والأوقات من نص الإجراء المولَّد (التقرير قائمة أعمال بلا تواريخ) */
    public static function undate(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $date = '(?:\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\/\d{1,2}\/\d{4})(?:\s*هـ)?(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?';
        $lines = [];
        foreach (preg_split('/\R/u', $v) as $line) {
            // سطر تاريخ فقط، أو «تاريخ …: 2026-11-12»
            if (preg_match('/^\s*(?:[^:\n]{0,40}:\s*)?'.$date.'\s*[.،]?\s*$/u', $line)) {
                continue;
            }
            $line = preg_replace('/\s{2,}/u', ' ', preg_replace('/\s*(?:بتاريخ|في|يوم)?\s*'.$date.'/u', '', $line));
            $lines[] = preg_replace('/^[\s—\-·:،]+|[\s—\-·:،]+$/u', '', $line); // trim() يكسر الحروف متعددة البايت
        }
        $out = trim(implode("\n", array_filter($lines, fn ($l) => $l !== '')));

        return $out === '' ? null : $out;
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

    /**
     * ما يصح دخوله التقرير من أعمال الفترة، كلٌّ بمرجع مصدره (event:ID / hearing:ID / collection:ID)
     * حتى لا يتكرر عند المزامنة ولا يعود ما حذفه المحامي. يعيد [الإجراءات، لقطة الملفات].
     */
    private function candidates(ClientReport $report): array
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
            ->orderBy('occurred_at')->orderBy('id')->get();
        foreach ($events as $e) {
            $key = $e->execution_request_id ? 'execution:'.$e->execution_request_id : ($e->legal_case_id ? 'case:'.$e->legal_case_id : 'general');
            $items[] = ['matter_key' => $key, 'occurred_on' => $e->occurred_at->copy()->setTimezone($tz)->toDateString(),
                'title' => self::clean($e->title) ?? $e->title, 'details' => in_array($e->type, self::TITLE_ONLY, true) ? null : self::clean($e->details),
                'source_ref' => 'event:'.$e->id];
            $touched[$key] = true;
        }
        // الجلسات التي انعقدت خلال الفترة (بلا نتائجها الداخلية)
        $hearings = Hearing::with('legalCase:id,client_id')->whereHas('legalCase', fn ($q) => $q->where('client_id', $cid))
            ->where('status', 'completed')->whereBetween('scheduled_at', [$from->utc(), $to->utc()])->orderBy('scheduled_at')->get();
        foreach ($hearings as $h) {
            $key = 'case:'.$h->legal_case_id;
            $on = $h->scheduled_at->copy()->setTimezone($tz);
            $exists = collect($items)->contains(fn ($i) => $i['matter_key'] === $key && $i['occurred_on'] === $on->toDateString() && str_contains($i['title'], 'جلسة'));
            if (! $exists) {
                $items[] = ['matter_key' => $key, 'occurred_on' => $on->toDateString(), 'title' => 'حضور '.Hearing::KINDS[$h->kind].($h->authority ? ' — '.$h->authority : ''),
                    'details' => null, 'source_ref' => 'hearing:'.$h->id];
            }
            $touched[$key] = true;
        }
        // المبالغ المقبوضة الظاهرة للعميل: لا تُدرج إجراءاتٍ، بل مؤشرًا بمجموعها حسب الملف والعملة
        $collected = Collection::where('client_id', $cid)->where('kind', 'received')->where('visible_to_client', true)
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('legal_case_id, execution_request_id, currency, sum(amount)::text as total')
            ->groupBy('legal_case_id', 'execution_request_id', 'currency')->toBase()->get()
            ->map(fn ($c) => ['matter' => $c->execution_request_id ? 'execution:'.$c->execution_request_id : ($c->legal_case_id ? 'case:'.$c->legal_case_id : 'general'),
                'currency' => $c->currency, 'amount' => $c->total])
            ->filter(fn ($c) => (float) $c['amount'] > 0)->values()->all();

        // لقطة الملفات: فقط القضايا والطلبات التي وقع عليها إجراء خلال الفترة
        $caseIds = collect(array_keys($touched))->filter(fn ($k) => str_starts_with($k, 'case:'))->map(fn ($k) => (int) substr($k, 5))->all();
        $execIds = collect(array_keys($touched))->filter(fn ($k) => str_starts_with($k, 'execution:'))->map(fn ($k) => (int) substr($k, 10))->all();
        $cases = LegalCase::with('filings')->where('client_id', $cid)->whereIn('id', $caseIds)->whereNull('archived_at')->where('work_status', '!=', 'draft')->get();
        $execs = ExecutionRequest::where('client_id', $cid)->whereIn('id', $execIds)->where('status', '!=', 'draft')->get();
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

        return [$items, $matters, $collected];
    }

    /** إعادة بناء كاملة: تحذف المولَّد كله (وما حُذف سابقًا يعود)، وتُبقي إضافات المحامي اليدوية */
    public function fill(ClientReport $report): void
    {
        DB::transaction(function () use ($report) {
            [$items, $matters, $collected] = $this->candidates($report);
            $report->items()->where('source', 'auto')->delete();
            foreach ($items as $i) {
                $report->items()->create($i + ['source' => 'auto']);
            }
            $report->forceFill(['matters' => $this->keepManualMatters($report, $matters), 'collected' => $collected, 'dismissed_refs' => null])->save();
        });
    }

    /**
     * مزامنة المسودة المفتوحة: تضيف كل إجراء جديد لم يدخلها بعد ولم يحذفه المحامي، وتحدّث مراحل الملفات.
     * لا تمس ما عدّله المحامي أو أضافه. التقرير المغلق أو المنشور لا يتغير.
     */
    public function sync(ClientReport $report): int
    {
        if (! $report->isOpen()) {
            return 0;
        }

        return DB::transaction(function () use ($report) {
            $report = ClientReport::lockForUpdate()->findOrFail($report->id);
            if (! $report->isOpen()) {
                return 0;
            }
            [$items, $matters, $collected] = $this->candidates($report);
            // التحصيل صار مؤشرًا لا إجراءً: تُزال بنوده القديمة من القائمة
            $report->items()->where('source', 'auto')->where(fn ($q) => $q->where('source_ref', 'like', 'collection:%')
                ->orWhere(fn ($q) => $q->whereNull('source_ref')->where('title', 'like', 'تحصيل مبلغ %')))->delete();
            $existing = $report->items()->get();
            // إجراءات أُنشئت قبل وجود المراجع: تُربط بمصدرها إن طابقته حتى لا تتكرر
            foreach ($existing->where('source', 'auto')->whereNull('source_ref') as $old) {
                $match = collect($items)->first(fn ($i) => $i['matter_key'] === $old->matter_key && $i['occurred_on'] === $old->occurred_on->toDateString() && $i['title'] === $old->title
                    && ! $existing->contains('source_ref', $i['source_ref']));
                if ($match) {
                    $old->forceFill(['source_ref' => $match['source_ref']])->save();
                }
            }
            $known = $existing->pluck('source_ref')->filter()->merge($report->dismissed_refs ?? [])->flip();
            $added = 0;
            foreach ($items as $i) {
                if (! isset($known[$i['source_ref']])) {
                    $report->items()->create($i + ['source' => 'auto']);
                    $added++;
                }
            }
            $report->forceFill(['matters' => $this->keepManualMatters($report, $matters), 'collected' => $collected])->save();

            return $added;
        });
    }

    /** يزامن كل المسودات المفتوحة للعميل التي تشمل فترتها هذا التاريخ */
    public function syncOpenFor(?int $clientId, $date = null): void
    {
        if (! $clientId) {
            return;
        }
        $day = $date ? CarbonImmutable::parse($date)->setTimezone(config('office.display_timezone'))->toDateString() : today_local()->toDateString();
        ClientReport::where('client_id', $clientId)->where('status', 'draft')
            ->whereDate('period_start', '<=', $day)->whereDate('period_end', '>=', $day)
            ->get()->each(fn (ClientReport $r) => $this->sync($r));
    }

    /** يُبقي في اللقطة أي ملف سابق ما زالت تحته إجراءات في التقرير، حتى لا تختفي */
    private function keepManualMatters(ClientReport $report, array $matters): array
    {
        $keys = collect($matters)->pluck('key')->all();
        $used = $report->items()->pluck('matter_key')->unique()->all();
        foreach ($report->matters ?? [] as $old) {
            if (! in_array($old['key'], $keys, true) && in_array($old['key'], $used, true)) {
                $matters[] = $old;
            }
        }

        return $matters;
    }

    /** بنية العرض المشتركة بين الشاشة وPDF */
    public function present(ClientReport $r, bool $forClient = false): array
    {
        $r->loadMissing('client:id,name', 'items');
        $byKey = $r->items->groupBy('matter_key');
        // المولَّد تُحذف منه التواريخ؛ ما كتبه المحامي بيده يُعرض كما كتبه
        $item = fn ($i) => ['id' => $i->id, 'on' => $i->occurred_on->toDateString(),
            'title' => $i->source === 'auto' ? (self::undate($i->title) ?? $i->title) : $i->title,
            'details' => $i->source === 'auto' ? self::undate($i->details) : $i->details, 'source' => $i->source, 'matter' => $i->matter_key];
        $money = collect($r->collected ?? []);
        $sum = fn ($rows) => collect($rows)->groupBy('currency')->map(fn ($g, $cur) => ['currency' => $cur, 'amount' => number_format($g->sum(fn ($x) => (float) $x['amount']), 2, '.', '')])
            ->filter(fn ($x) => (float) $x['amount'] > 0)->values()->all();
        // لا يظهر في التقرير إلا ملف وقع فيه إجراء خلال الفترة
        $matters = collect($r->matters ?? [])
            ->when($forClient, fn ($m) => $m->reject(fn ($x) => $x['hidden'] ?? false))
            ->map(fn ($m) => $m + ['items' => ($byKey[$m['key']] ?? collect())->map($item)->values()->all(),
                'collected' => $sum($money->where('matter', $m['key']))])
            ->filter(fn ($m) => count($m['items']) > 0)->values()->all();
        $general = ($byKey['general'] ?? collect())->map($item)->values()->all();
        $hiddenKeys = collect($r->matters ?? [])->where('hidden', true)->pluck('key')->all();
        $collectedTotal = $sum($money->reject(fn ($c) => $forClient && in_array($c['matter'], $hiddenKeys, true)));
        $visibleItems = collect($matters)->sum(fn ($m) => count($m['items'])) + count($general);

        return [
            'id' => $r->id, 'title' => $r->title, 'period' => $r->period, 'periodLabel' => ClientReport::PERIODS[$r->period],
            'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(),
            'startHijri' => $this->cal->hijri($r->period_start), 'endHijri' => $this->cal->hijri($r->period_end),
            'client' => ['id' => $r->client_id, 'name' => $r->client->name],
            'intro' => $r->intro, 'nextSteps' => $r->next_steps, 'status' => $r->status, 'statusLabel' => ClientReport::STATUSES[$r->status],
            'publishedAt' => $r->published_at?->toIso8601String(), 'closedAt' => $r->closed_at?->toIso8601String(), 'lockVersion' => $r->lock_version,
            'matters' => $matters, 'general' => $general,
            'stats' => [
                'cases' => collect($matters)->where('kind', 'case')->count(),
                'executions' => collect($matters)->where('kind', 'execution')->count(),
                'actions' => $visibleItems,
                'hearings' => collect($matters)->flatMap(fn ($m) => $m['items'])->filter(fn ($i) => str_contains($i['title'], 'جلسة'))->count(),
                'collected' => $collectedTotal, // فارغ إن لم يُحصَّل شيء، فلا يظهر المؤشر
            ],
        ];
    }
}
