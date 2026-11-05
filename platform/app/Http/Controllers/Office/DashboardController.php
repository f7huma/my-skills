<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\PowerOfAttorney;
use App\Models\Setting;
use App\Services\AgendaService;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(AgendaService $agenda)
    {
        $warn = (int) Setting::get('poa_expiry_warning_days', config('office.poa_expiry_warning_days'));
        $today = today_local();

        return Inertia::render('Office/Dashboard', [
            'agenda' => $agenda->week(),
            'claims' => Claim::with('client:id,name')->whereIn('status', ['under_review', 'needs_completion'])->orderBy('submitted_at')->limit(8)->get()
                ->map(fn ($c) => ['id' => $c->id, 'reference' => $c->reference, 'client' => $c->client->name, 'opponent' => $c->opponent_name,
                    'amount' => $c->amount, 'currency' => $c->currency, 'status' => $c->status, 'statusLabel' => Claim::STATUSES[$c->status],
                    'missing' => $c->missing_documents, 'submittedAt' => $c->submitted_at?->toIso8601String()]),
            'hearings' => Hearing::with('legalCase:id,reference,title,client_id', 'legalCase.client:id,name')->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->limit(6)->get()
                ->map(fn ($h) => ['id' => $h->id, 'kind' => Hearing::KINDS[$h->kind], 'at' => $h->scheduled_at->toIso8601String(), 'authority' => $h->authority,
                    'caseId' => $h->legal_case_id, 'ref' => $h->legalCase->reference, 'title' => $h->legalCase->title, 'client' => $h->legalCase->client->name]),
            'deadlines' => Deadline::with('legalCase:id,reference', 'executionRequest:id,reference')->whereIn('status', ['open', 'alerted'])
                ->orderByRaw('end_on asc nulls first')->limit(6)->get()
                ->map(fn ($d) => ['id' => $d->id, 'reason' => $d->reason, 'end' => $d->end_on?->toDateString(), 'certainty' => $d->certainty,
                    'certaintyLabel' => Deadline::CERTAINTY[$d->certainty], 'alerted' => $d->status === 'alerted',
                    'ref' => $d->legalCase?->reference ?? $d->executionRequest?->reference]),
            'poas' => PowerOfAttorney::with('client:id,name')->where('status', 'active')->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', $today->copy()->addDays($warn)->toDateString())->orderBy('expires_on')->limit(6)->get()
                ->map(fn ($p) => ['id' => $p->id, 'number' => $p->number, 'client' => $p->client->name, 'clientId' => $p->client_id,
                    'expires' => $p->expires_on->toDateString(), 'state' => $p->effectiveStatus($warn)]),
            'counters' => [
                'activeCases' => LegalCase::whereIn('work_status', LegalCase::ACTIVE_STATUSES)->count(),
                'resumable' => LegalCase::whereIn('work_status', LegalCase::RESUMABLE_STATUSES)->count(),
                'alerted' => Deadline::where('status', 'alerted')->count(),
                'needsDetermination' => Deadline::where('status', '!=', 'closed')->where('certainty', 'needs_determination')->count(),
            ],
            'clients' => Client::whereNull('archived_at')->withCount(['cases as active_cases_count' => fn ($q) => $q->whereIn('work_status', LegalCase::ACTIVE_STATUSES)])
                ->orderBy('name')->limit(12)->get(['id', 'name', 'type', 'reference'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'reference' => $c->reference, 'active' => $c->active_cases_count]),
            'insights' => $this->insights($today),
        ]);
    }

    /** أرقام اللوحة: المقبوض فعليًا بالريال لآخر ستة أشهر، وتوزيع القضايا على المراحل */
    private function insights($today): array
    {
        $from = $today->copy()->startOfMonth()->subMonths(5);
        $rows = Collection::query()->where('kind', 'received')->where('currency', 'SAR')
            ->whereDate('occurred_on', '>=', $from->toDateString())
            ->selectRaw("to_char(occurred_on, 'YYYY-MM') as m, sum(amount)::text as total")->groupBy('m')->pluck('total', 'm');
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $from->copy()->addMonths($i)->format('Y-m');
            $months[] = ['month' => $m, 'total' => (float) ($rows[$m] ?? 0)];
        }
        $stages = LegalCase::whereNull('archived_at')->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');

        return [
            'months' => $months,
            'collectedThisMonth' => end($months)['total'],
            'stages' => collect(LegalCase::STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($stages[$key] ?? 0)])->values(),
        ];
    }

    public function search(Request $request, SearchService $search)
    {
        $q = (string) $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '';

        return response()->json(['results' => $search->search($request->user(), $q)]);
    }
}
