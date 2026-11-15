<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Office\ClientReportController;
use App\Models\Client;
use App\Models\ClientReport;
use App\Services\Audit;
use App\Services\Media\ClientLogo;
use App\Services\Reports\ClientReportService;
use App\Services\Reports\PdfRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** تقارير العميل: المنشورة فقط، ولعملاء عضوية المتابعة السارية فقط — وإلا 404 */
class ReportController extends Controller
{
    public function __construct(private ClientReportService $svc) {}

    private function find(Request $request, int $id): ClientReport
    {
        $r = ClientReport::where('status', 'published')->whereIn('client_id', $request->user()->clientIdsFor('view'))->find($id);
        abort_unless($r, 404);

        return $r;
    }

    public function index(Request $request)
    {
        return Inertia::render('Portal/Reports/Index', [
            'reports' => ClientReport::with('client:id,name')->where('status', 'published')->whereIn('client_id', $request->user()->clientIdsFor('view'))
                ->orderByDesc('period_start')->get()
                ->map(fn (ClientReport $r) => ['id' => $r->id, 'title' => $r->title, 'client' => $r->client->name, 'period' => ClientReport::PERIODS[$r->period],
                    'start' => $r->period_start->toDateString(), 'end' => $r->period_end->toDateString(), 'publishedAt' => $r->published_at?->toIso8601String()]),
        ]);
    }

    public function show(Request $request, int $report)
    {
        $r = $this->find($request, $report);
        Audit::log('client_report.viewed', $r);

        return Inertia::render('Portal/Reports/Show', ['report' => $this->svc->present($r, true)]);
    }

    /** شعار العميل لمن يتابعه فقط */
    public function logo(Request $request, int $client)
    {
        abort_unless(in_array($client, $request->user()->clientIdsFor('view'), false), 404);

        return ClientLogo::response(Client::findOrFail($client));
    }

    public function pdf(Request $request, int $report, PdfRenderer $pdf)
    {
        $r = $this->find($request, $report);
        Audit::log('client_report.pdf', $r);

        return ClientReportController::pdfResponse($this->svc->present($r, true), $pdf);
    }
}
