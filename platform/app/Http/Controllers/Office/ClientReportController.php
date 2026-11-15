<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReport;
use App\Models\ClientReportItem;
use App\Services\Audit;
use App\Services\Media\ClientLogo;
use App\Services\Reports\ClientReportService;
use App\Services\Reports\PdfRenderer;
use App\Services\Reports\WorkCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** التقارير الدورية: إعداد وتحرير كامل للمحامي، ثم نشر لبوابة العميل بعد الاعتماد */
class ClientReportController extends Controller
{
    private const DATE_MESSAGES = [
        'start.required' => 'حدّد تاريخ بداية الفترة.', 'end.required' => 'حدّد تاريخ نهاية الفترة.',
        'end.after_or_equal' => 'تاريخ النهاية يجب أن يكون في يوم البداية أو بعده.',
    ];

    public function __construct(private ClientReportService $svc) {}

    /** شعار العميل: يُرفع من محرر التقرير ويُستخدم في كل تقاريره */
    public function storeLogo(Request $request, Client $client)
    {
        $request->validate(['logo' => 'required|file|mimes:png,jpg,jpeg,webp|max:3072'], [
            'logo.required' => 'اختر صورة الشعار.', 'logo.mimes' => 'الشعار يجب أن يكون PNG أو JPG أو WEBP.', 'logo.max' => 'حجم الصورة يتجاوز 3 ميجابايت.',
        ]);
        ClientLogo::store($client, $request->file('logo'));
        Audit::log('client.logo_updated', $client);

        return back()->with('success', 'حُفظ شعار العميل، وسيظهر في تقاريره.');
    }

    public function destroyLogo(Client $client)
    {
        ClientLogo::remove($client);
        Audit::log('client.logo_removed', $client);

        return back()->with('success', 'أُزيل شعار العميل.');
    }

    public function logo(Client $client)
    {
        return ClientLogo::response($client);
    }

    /** تعديل مدى التقرير (البداية والنهاية) بعد إنشائه */
    public function period(Request $request, ClientReport $report)
    {
        abort_if($report->isPublished(), 422, 'اسحب التقرير من صفحة العميل أولًا.');
        $data = $request->validate([
            'period' => ['required', Rule::in(array_keys(ClientReport::PERIODS))], 'start' => 'required|date', 'end' => 'required|date|after_or_equal:start',
        ], self::DATE_MESSAGES);
        $this->svc->changePeriod($report, $data['period'], $data['start'], $data['end']);
        Audit::log('client_report.period_changed', $report, ['start' => $data['start'], 'end' => $data['end']]);

        return back()->with('success', 'عُدّلت فترة التقرير، وحُدّثت الأعمال وفقها.');
    }

    public function index(Request $request)
    {
        $clientId = $request->integer('client') ?: null;

        return Inertia::render('Office/ClientReports/Index', [
            'reports' => ClientReport::with('client:id,name')->withCount('items')->when($clientId, fn ($q) => $q->where('client_id', $clientId))
                ->orderByDesc('period_start')->orderByDesc('id')->limit(200)->get()
                ->map(fn (ClientReport $r) => ['id' => $r->id, 'title' => $r->title, 'client' => $r->client->name, 'clientId' => $r->client_id,
                    'period' => ClientReport::PERIODS[$r->period], 'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(),
                    'status' => $r->status, 'statusLabel' => ClientReport::STATUSES[$r->status], 'items' => $r->items_count,
                    'publishedAt' => $r->published_at?->toIso8601String(), 'closedAt' => $r->closed_at?->toIso8601String(), 'autoSync' => (bool) $r->auto_sync]),
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'preset' => ['client' => $clientId, 'today' => today_local()->toDateString(),
                'ranges' => collect(['weekly', 'monthly', 'yearly'])->mapWithKeys(fn ($p) => [$p => collect($this->svc->range($p, today_local()->toDateString()))->map->toDateString()->all()])],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id', 'period' => ['required', Rule::in(array_keys(ClientReport::PERIODS))],
            'start' => 'required|date', 'end' => 'required|date|after_or_equal:start', 'auto_sync' => 'boolean',
        ], self::DATE_MESSAGES);
        $report = $this->svc->generate(Client::findOrFail($data['client_id']), $data['period'], $data['start'], $data['end'], $request->user(), (bool) ($data['auto_sync'] ?? false));
        Audit::log('client_report.created', $report, ['period' => $report->period]);

        return redirect()->route('client-reports.show', $report)->with('success', $report->auto_sync ? 'فُتح التقرير، وسيعكس أعمال المنصة تلقائيًا حتى تُغلقه.' : 'فُتح التقرير. أضف أعمالك من «إضافة عمل».');
    }

    public function show(ClientReport $report)
    {
        $this->svc->sync($report);
        $report->refresh();

        return Inertia::render('Office/ClientReports/Show', ['report' => $this->svc->present($report), 'catalog' => WorkCatalog::forClient()]);
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
        abort_if($report->isPublished() || ! $report->auto_sync, 422, 'إعادة البناء متاحة عند تفعيل عكس أعمال المنصة.');
        $this->svc->fill($report);
        Audit::log('client_report.regenerated', $report);

        return back()->with('success', 'أُعيد بناء الإجراءات من سجل الأعمال، وبقيت إضافاتك اليدوية كما هي.');
    }

    /** عمل منظَّم من قائمة أعمال المكتب، بحقوله الخاصة */
    public function storeItem(Request $request, ClientReport $report)
    {
        $data = $request->validate(['kind' => 'required|string|max:40', 'fields' => 'array', 'notes' => 'nullable|string|max:3000'], ['kind.required' => 'اختر نوع العمل.']);
        [$fields, $title] = WorkCatalog::validate($data['kind'], $data['fields'] ?? []);
        $on = min(today_local()->toDateString(), $report->period_end->toDateString());
        $item = $report->items()->create([
            'matter_key' => 'general', 'occurred_on' => max($on, $report->period_start->toDateString()), 'source' => 'manual',
            'kind' => $data['kind'], 'fields' => $fields, 'title' => $title, 'details' => $data['notes'] ?? null,
        ]);
        Audit::log('client_report.item_added', $report, ['item' => $item->id]);

        return back()->with('success', 'أُضيف العمل إلى التقرير.');
    }

    public function updateItem(Request $request, ClientReportItem $item)
    {
        $data = $request->validate(['kind' => 'required|string|max:40', 'fields' => 'array', 'notes' => 'nullable|string|max:3000']);
        // ما عكسته المنصة يُعدَّل نصه فقط
        $kind = $item->source === 'auto' ? 'other' : $data['kind'];
        [$fields, $title] = WorkCatalog::validate($kind, $data['fields'] ?? []);
        $item->update($item->source === 'auto'
            ? ['title' => $fields['action_label'], 'details' => $data['notes'] ?? null]
            : ['kind' => $kind, 'fields' => $fields, 'title' => $title, 'details' => $data['notes'] ?? null]);
        Audit::log('client_report.item_updated', $item->report, ['item' => $item->id]);

        return back()->with('success', 'عُدّل العمل.');
    }

    /** تشغيل عكس أعمال المنصة أو إيقافه. الإيقاف يزيل ما جلبته المنصة ويُبقي أعمالك */
    public function autoSync(Request $request, ClientReport $report)
    {
        abort_if($report->isPublished(), 422, 'اسحب التقرير من صفحة العميل أولًا.');
        $on = $request->validate(['auto_sync' => 'required|boolean'])['auto_sync'];
        $report->forceFill(['auto_sync' => $on])->save();
        if ($on) {
            $report->isOpen() ? $this->svc->fill($report) : null;
        } else {
            $report->items()->where('source', 'auto')->delete();
            $report->forceFill(['matters' => [], 'collected' => null, 'dismissed_refs' => null])->save();
        }
        Audit::log('client_report.auto_sync', $report, ['on' => $on]);

        return back()->with('success', $on ? 'سيعكس التقرير أعمال المنصة تلقائيًا.' : 'أُوقف عكس أعمال المنصة، وبقيت أعمالك كما هي.');
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
        $r['clientLogo'] = ClientLogo::dataUri(Client::findOrFail($r['client']['id']));
        $r['logo'] = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('brand/logo-mark.png')));
        $r['issuedAt'] = now(config('office.display_timezone'))->format('Y-m-d');
        $html = view('reports.client-report', ['r' => $r])->render();
        $bytes = $pdf->render($html, $r['title']);
        $name = 'report-'.$r['start'].'-'.$r['end'].'.pdf';

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'no-store, private']);
    }
}
