<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReport;
use App\Models\ClientReportItem;
use App\Services\Audit;
use App\Services\Calendar;
use App\Services\Reports\ClientReportService;
use App\Services\Reports\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** التقارير الدورية: إعداد وتحرير كامل للمحامي، ثم نشر لبوابة العميل بعد الاعتماد */
class ClientReportController extends Controller
{
    public function __construct(private ClientReportService $svc) {}

    public function index(Request $request)
    {
        $clientId = $request->integer('client') ?: null;

        return Inertia::render('Office/ClientReports/Index', [
            'reports' => ClientReport::with('client:id,name')->withCount('items')->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->orderByDesc('period_start')->orderByDesc('id')->limit(200)->get()
                ->map(fn (ClientReport $r) => ['id' => $r->id, 'title' => $r->title, 'client' => $r->client->name, 'clientId' => $r->client_id,
                    'period' => ClientReport::PERIODS[$r->period], 'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(),
                    'status' => $r->status, 'statusLabel' => ClientReport::STATUSES[$r->status], 'items' => $r->items_count,
                    'publishedAt' => $r->published_at?->toIso8601String(), 'closedAt' => $r->closed_at?->toIso8601String()]),
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'preset' => ['client' => $clientId, 'today' => today_local()->toDateString()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id', 'period' => ['required', Rule::in(array_keys(ClientReport::PERIODS))], 'anchor' => 'required|date',
        ], ['anchor.required' => 'اختر تاريخًا داخل الفترة.']);
        $report = $this->svc->generate(Client::findOrFail($data['client_id']), $data['period'], $data['anchor'], $request->user());
        Audit::log('client_report.created', $report, ['period' => $report->period]);

        return redirect()->route('client-reports.show', $report)->with('success', 'فُتح التقرير مسودةً من أعمال الفترة، وسيلتقط كل إجراء جديد تلقائيًا حتى تُغلقه.');
    }

    public function show(ClientReport $report)
    {
        $this->svc->sync($report);
        $report->refresh();

        return Inertia::render('Office/ClientReports/Show', ['report' => $this->svc->present($report)]);
    }

    public function update(Request $request, ClientReport $report)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200', 'intro' => 'nullable|string|max:5000', 'next_steps' => 'nullable|string|max:5000',
            'hidden' => 'array', 'hidden.*' => 'string|max:40', 'lock_version' => 'required|integer',
        ]);
        abort_if((int) $data['lock_version'] !== $report->lock_version, 409, 'عُدّل التقرير من نافذة أخرى. حدّث الصفحة.');
        $hidden = $data['hidden'] ?? [];
        $report->forceFill([
            'title' => $data['title'], 'intro' => $data['intro'] ?? null, 'next_steps' => $data['next_steps'] ?? null,
            'matters' => collect($report->matters)->map(fn ($m) => ['hidden' => in_array($m['key'], $hidden, true)] + $m)->all(),
            'lock_version' => $report->lock_version + 1,
        ])->save();
        Audit::log('client_report.updated', $report);

        return back()->with('success', 'حُفظ التقرير.');
    }

    public function regenerate(ClientReport $report)
    {
        abort_if($report->isPublished(), 422, 'اسحب التقرير من صفحة العميل أولًا.');
        $this->svc->fill($report);
        Audit::log('client_report.regenerated', $report);

        return back()->with('success', 'أُعيد بناء الإجراءات من سجل الأعمال، وبقيت إضافاتك اليدوية كما هي.');
    }

    private function matterKeys(ClientReport $report): array
    {
        return collect($report->matters)->pluck('key')->push('general')->all();
    }

    public function storeItem(Request $request, ClientReport $report)
    {
        $data = $request->validate([
            'matter_key' => ['required', Rule::in($this->matterKeys($report))],
            'title' => 'required|string|max:300', 'details' => 'nullable|string|max:3000',
        ], ['title.required' => 'اكتب الإجراء.']);
        // لا تاريخ يُعرض للإجراءات؛ يُحفظ داخليًا لترتيب القائمة فقط (آخر القائمة)
        $on = min(today_local()->toDateString(), $report->period_end->toDateString());
        $item = $report->items()->create($data + ['occurred_on' => max($on, $report->period_start->toDateString()), 'source' => 'manual']);
        Audit::log('client_report.item_added', $report, ['item' => $item->id]);

        return back()->with('success', 'أُضيف الإجراء.');
    }

    public function updateItem(Request $request, ClientReportItem $item)
    {
        $data = $request->validate([
            'matter_key' => ['required', Rule::in($this->matterKeys($item->report))],
            'title' => 'required|string|max:300', 'details' => 'nullable|string|max:3000',
        ]);
        $item->update($data);
        Audit::log('client_report.item_updated', $item->report, ['item' => $item->id]);

        return back()->with('success', 'عُدّل الإجراء.');
    }

    public function destroyItem(ClientReportItem $item)
    {
        $report = $item->report;
        if ($item->source === 'auto' && $item->source_ref) {
            // لا يعود الإجراء المحذوف في المزامنة التلقائية التالية
            $report->forceFill(['dismissed_refs' => array_values(array_unique([...($report->dismissed_refs ?? []), $item->source_ref]))])->save();
        }
        $item->delete();
        Audit::log('client_report.item_deleted', $report, ['item' => $item->id]);

        return back()->with('success', 'حُذف الإجراء من التقرير.');
    }

    /** إغلاق المسودة: تتوقف المزامنة التلقائية ويثبت محتوى التقرير */
    public function close(ClientReport $report)
    {
        abort_unless($report->isOpen(), 422, 'التقرير مغلق بالفعل.');
        $this->svc->sync($report); // آخر التقاط قبل التثبيت
        $report->refresh()->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
        Audit::log('client_report.closed', $report);

        return back()->with('success', 'أُغلق التقرير واكتمل. راجعه ثم اعتمده للنشر.');
    }

    /** إعادة الفتح: يعود مسودة تلتقط الجديد (ويُسحب من صفحة العميل إن كان منشورًا) */
    public function reopen(ClientReport $report)
    {
        $report->forceFill(['status' => 'draft', 'closed_at' => null, 'published_at' => null, 'published_by' => null])->save();
        Audit::log('client_report.reopened', $report);
        $this->svc->sync($report);

        return back()->with('success', 'أُعيد فتح التقرير، وسيلتقط الإجراءات الجديدة تلقائيًا.');
    }

    public function publish(Request $request, ClientReport $report)
    {
        if ($report->isOpen()) {
            $this->svc->sync($report);
            $report->refresh();
        }
        $report->forceFill(['status' => 'published', 'closed_at' => $report->closed_at ?? now(), 'published_at' => now(), 'published_by' => $request->user()->id])->save();
        Audit::log('client_report.published', $report);

        return back()->with('success', 'اعتُمد التقرير ونُشر في صفحة العميل.');
    }

    public function unpublish(ClientReport $report)
    {
        $report->forceFill(['status' => 'closed', 'published_at' => null, 'published_by' => null])->save();
        Audit::log('client_report.unpublished', $report);

        return back()->with('success', 'سُحب التقرير من صفحة العميل، وبقي مكتملًا دون تغيير.');
    }

    public function destroy(ClientReport $report)
    {
        $client = $report->client_id;
        Audit::log('client_report.deleted', $report);
        $report->delete();

        return redirect()->route('client-reports.index', ['client' => $client])->with('success', 'حُذف التقرير.');
    }

    public function pdf(ClientReport $report, PdfRenderer $pdf)
    {
        $this->svc->sync($report);
        $report->refresh();
        Audit::log('client_report.pdf', $report);

        return self::pdfResponse($this->svc->present($report, true), $pdf);
    }

    /** ملف PDF بهوية المكتب — يستخدمه المحامي والعميل */
    public static function pdfResponse(array $r, PdfRenderer $pdf)
    {
        $r['logo'] = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('brand/logo-mark.png')));
        $r['issuedAt'] = now(config('office.display_timezone'))->format('Y-m-d');
        $r['issuedHijri'] = app(Calendar::class)->hijri(today_local());
        $html = view('reports.client-report', ['r' => $r])->render();
        $bytes = $pdf->render($html, $r['title']);
        $name = 'report-'.$r['start'].'-'.$r['end'].'.pdf';

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'no-store, private']);
    }
}
