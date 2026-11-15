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
use App\Services\Media\ClientLogo;
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
            'yearly' => [$d->startOfYear(), $d->endOfYear()->startOfDay()],
            default => [$d->startOfMonth(), $d->endOfMonth()->startOfDay()],
        };
    }

    public function title(string $period, CarbonImmutable $start, CarbonImmutable $end): string
    {
        $fmt = fn (CarbonImmutable $x, string $p) => (new \IntlDateFormatter('ar_SA@calendar=gregorian;numbers=latn', 0, 0, config('office.display_timezone'), null, $p))->format($x);

        $span = $fmt($start, $start->year === $end->year ? 'd MMMM' : 'd MMMM y').' إلى '.$fmt($end, 'd MMMM y');
        // العنوان المختصر فقط إن طابقت الحدود الشهر أو السنة كاملة؛ وإلا يُذكر المدى
        $fullMonth = $start->isSameDay($start->startOfMonth()) && $end->isSameDay($start->endOfMonth());
        $fullYear = $start->isSameDay($start->startOfYear()) && $end->isSameDay($start->endOfYear());

        return match ($period) {
            'weekly' => 'التقرير الأسبوعي — '.$span,
            'monthly' => 'التقرير الشهري — '.($fullMonth ? $fmt($start, 'MMMM y') : $span),
            'yearly' => 'التقرير السنوي — '.($fullYear ? $start->year : $span),
            default => 'تقرير الأعمال — '.$span,
        };
    }

    public function generate(Client $client, string $period, string $from, string $to, User $by, bool $autoSync = false): ClientReport
    {
        $tz = config('office.display_timezone');
        $start = CarbonImmutable::parse($from, $tz)->startOfDay();
        $end = CarbonImmutable::parse($to, $tz)->startOfDay();

        return DB::transaction(function () use ($client, $period, $start, $end, $by, $autoSync) {
            $report = ClientReport::create([
                'client_id' => $client->id, 'period' => $period, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'title' => $this->title($period, $start, $end), 'matters' => [], 'status' => 'draft', 'created_by' => $by->id, 'auto_sync' => $autoSync,
            ]);
            if ($autoSync) {
                $this->fill($report);
            }

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
        if (! $report->isOpen() || ! $report->auto_sync) {
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

    /**
     * تعديل بداية الفترة ونهايتها: يُزال من المولَّد ما خرج عن المدى الجديد، ويدخل ما صار داخله.
     * ما أضافه المحامي يدويًا يبقى. يُحدَّث العنوان إن كان هو العنوان التلقائي السابق.
     */
    public function changePeriod(ClientReport $report, string $period, string $from, string $to): void
    {
        DB::transaction(function () use ($report, $period, $from, $to) {
            $tz = config('office.display_timezone');
            $oldTitle = $this->title($report->period, CarbonImmutable::parse($report->period_start->toDateString(), $tz), CarbonImmutable::parse($report->period_end->toDateString(), $tz));
            $start = CarbonImmutable::parse($from, $tz)->startOfDay();
            $end = CarbonImmutable::parse($to, $tz)->startOfDay();
            $report->forceFill([
                'period' => $period, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString(),
                'title' => $report->title === $oldTitle ? $this->title($period, $start, $end) : $report->title,
            ])->save();
            $report->items()->where('source', 'auto')->where(fn ($q) => $q->whereDate('occurred_on', '<', $start->toDateString())->orWhereDate('occurred_on', '>', $end->toDateString()))->delete();
            $report->items()->where('source', 'manual')->whereDate('occurred_on', '<', $start->toDateString())->update(['occurred_on' => $start->toDateString()]);
            $report->items()->where('source', 'manual')->whereDate('occurred_on', '>', $end->toDateString())->update(['occurred_on' => $end->toDateString()]);
            if ($report->isOpen()) {
                $this->sync($report);
            } else {
                // المغلق لا يلتقط جديدًا تلقائيًا، لكن تغيير المدى صريح من المحامي فيُحدَّث وفقه
                $report->forceFill(['status' => 'draft'])->save();
                $this->sync($report);
                $report->forceFill(['status' => 'closed'])->save();
            }
        });
    }

    /** يزامن كل المسودات المفتوحة للعميل التي تشمل فترتها هذا التاريخ */
    public function syncOpenFor(?int $clientId, $date = null): void
    {
        if (! $clientId) {
            return;
        }
        $day = $date ? CarbonImmutable::parse($date)->setTimezone(config('office.display_timezone'))->toDateString() : today_local()->toDateString();
        ClientReport::where('client_id', $clientId)->where('status', 'draft')->where('auto_sync', true)
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

    /**
     * بنية العرض المشتركة بين الشاشة وPDF:
     * - groups: أعمال المكتب المنظَّمة مجمّعة حسب المرحلة (الصلح، الدعاوى، التنفيذ...).
     * - platform: ما عكسته المنصة تلقائيًا (إن فُعِّل العكس)، مجمّعًا حسب الملف.
     */
    public function present(ClientReport $r, bool $forClient = false): array
    {
        $r->loadMissing('client:id,name,logo_path', 'items');
        $manual = $r->items->where('source', 'manual');
        $auto = $r->items->where('source', 'auto');

        $groups = [];
        $collectedManual = 0.0;
        foreach ($manual as $i) {
            $p = WorkCatalog::present($i->kind, $i->fields);
            $collectedManual += $p['collected'] ?? 0;
            $groups[$p['group']][] = ['id' => $i->id, 'kind' => $i->kind ?? 'other', 'headline' => $p['headline'] ?? $i->title, 'facts' => $p['facts'],
                'notes' => $i->details, 'fields' => $i->fields ?? ($i->kind ? [] : ['action_label' => $i->title]), 'source' => 'manual'];
        }
        $groups = collect(WorkCatalog::GROUPS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'items' => $groups[$key] ?? []])
            ->filter(fn ($g) => count($g['items']))->values()->all();

        // ما عكسته المنصة: يُعرض حسب الملف كما كان
        $byKey = $auto->groupBy('matter_key');
        $plain = fn ($i) => ['id' => $i->id, 'kind' => null, 'headline' => self::undate($i->title) ?? $i->title, 'facts' => [],
            'notes' => self::undate($i->details), 'fields' => [], 'source' => 'auto'];
        $money = collect($r->auto_sync ? ($r->collected ?? []) : []);
        $sum = fn ($rows) => collect($rows)->groupBy('currency')->map(fn ($g, $cur) => ['currency' => $cur, 'amount' => number_format($g->sum(fn ($x) => (float) $x['amount']), 2, '.', '')])
            ->filter(fn ($x) => (float) $x['amount'] > 0)->values()->all();
        $matters = collect($r->matters ?? [])
            ->when($forClient, fn ($m) => $m->reject(fn ($x) => $x['hidden'] ?? false))
            ->map(fn ($m) => $m + ['items' => ($byKey[$m['key']] ?? collect())->map($plain)->values()->all(), 'collected' => $sum($money->where('matter', $m['key']))])
            ->filter(fn ($m) => count($m['items']) > 0)->values()->all();
        $platformGeneral = ($byKey['general'] ?? collect())->map($plain)->values()->all();

        $hiddenKeys = collect($r->matters ?? [])->where('hidden', true)->pluck('key')->all();
        $collected = $money->reject(fn ($c) => $forClient && in_array($c['matter'], $hiddenKeys, true))->all();
        if ($collectedManual > 0) {
            $collected[] = ['currency' => 'SAR', 'amount' => (string) $collectedManual];
        }
        $platformCount = collect($matters)->sum(fn ($m) => count($m['items'])) + count($platformGeneral);

        return [
            'id' => $r->id, 'title' => $r->title, 'period' => $r->period, 'periodLabel' => ClientReport::PERIODS[$r->period],
            'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(),
            'client' => ['id' => $r->client_id, 'name' => $r->client->name, 'logo' => ClientLogo::url($r->client, $forClient)],
            'intro' => $r->intro, 'nextSteps' => $r->next_steps, 'status' => $r->status, 'statusLabel' => ClientReport::STATUSES[$r->status],
            'publishedAt' => $r->published_at?->toIso8601String(), 'closedAt' => $r->closed_at?->toIso8601String(), 'lockVersion' => $r->lock_version,
            'autoSync' => (bool) $r->auto_sync,
            'groups' => $groups,
            'platform' => ['matters' => $matters, 'general' => $platformGeneral, 'count' => $platformCount],
            'stats' => [
                'total' => $manual->count() + $platformCount,
                'groups' => collect($groups)->map(fn ($g) => ['key' => $g['key'], 'label' => $g['label'], 'count' => count($g['items'])])->all(),
                'collected' => $sum($collected),
            ],
        ];
    }
}
