<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Reports\PdfRenderer;
use App\Services\Reports\ReportBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Office/Reports/Index', [
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'cases' => LegalCase::with('client:id,name')->orderByDesc('updated_at')->limit(500)->get(['id', 'reference', 'title', 'client_id'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->reference.' — '.$c->title, 'clientId' => $c->client_id]),
            'preset' => $request->only(['type', 'client', 'case']),
        ]);
    }

    private function data(Request $request, ReportBuilder $builder): array
    {
        $v = $request->validate([
            'type' => 'required|in:client,case', 'id' => 'required|integer', 'variant' => 'required|in:client,internal',
            'timeline' => 'boolean', 'parties' => 'boolean', 'notes' => 'boolean', 'tasks' => 'boolean', 'hearings' => 'boolean',
            'case_status' => 'nullable|in:all,open,completed',
        ]);
        $opt = ['timeline' => $request->boolean('timeline', true), 'case_status' => $v['case_status'] ?? 'all'];
        if ($v['variant'] === 'internal') {
            $opt += ['parties' => $request->boolean('parties'), 'notes' => $request->boolean('notes'), 'tasks' => $request->boolean('tasks'), 'hearings' => $request->boolean('hearings')];
        }
        $report = $v['type'] === 'client'
            ? $builder->clientReport(Client::findOrFail($v['id']), $v['variant'], $opt)
            : $builder->caseReport(LegalCase::findOrFail($v['id']), $v['variant'], $opt);
        $report['issuedAt'] = now(config('office.display_timezone'))->format('Y-m-d').' الساعة '.now(config('office.display_timezone'))->format('H:i');
        $report['issuedHijri'] = app(\App\Services\Calendar::class)->hijri(today_local());
        $report['logo'] = 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path('brand/logo-mark.png')));

        return [$v, $report];
    }

    public function preview(Request $request, ReportBuilder $builder)
    {
        [$v, $report] = $this->data($request, $builder);
        Audit::log('report.previewed', null, ['type' => $v['type'], 'id' => $v['id'], 'variant' => $v['variant']]);

        return response()->view('reports.document', ['r' => $report, 'screen' => true])
            ->header('Cache-Control', 'no-store, private')
            ->header('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src 'self'; frame-ancestors 'self'")
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function pdf(Request $request, ReportBuilder $builder, PdfRenderer $pdf)
    {
        [$v, $report] = $this->data($request, $builder);
        $html = view('reports.document', ['r' => $report, 'screen' => false])->render();
        $bytes = $pdf->render($html, $report['title']);
        Audit::log('report.generated', null, ['type' => $v['type'], 'id' => $v['id'], 'variant' => $v['variant']]);
        $name = ($v['type'] === 'client' ? 'client-report-' : 'case-report-').$v['id'].'-'.now(config('office.display_timezone'))->format('Ymd').'.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
