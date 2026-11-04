<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Portal;
use App\Models\Claim;
use App\Models\Collection;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * بوابة العميل: هوية العميل تُستمد من العضويات السارية على الخادم في كل طلب.
 * أي معرّف يرسله المتصفح يُقيَّد بها، وأي سجل خارجها يعطي 404.
 */
class PortalController extends Controller
{
    public function home(Request $request)
    {
        $user = $request->user();
        $viewIds = $user->clientIdsFor('view');
        $claimIds = $user->clientIdsFor('claims');

        $cases = LegalCase::with('filings:id,legal_case_id,case_number,registered_on')->whereIn('client_id', $viewIds)->whereNull('archived_at')->where('work_status', '!=', 'draft')
            ->orderByDesc('updated_at')->get();
        $executions = ExecutionRequest::whereIn('client_id', $viewIds)->where('status', '!=', 'draft')->orderByDesc('updated_at')->get();
        $received = Collection::totals(Collection::query()->whereIn('client_id', $viewIds)->where('visible_to_client', true));
        $claims = Claim::whereIn('client_id', $claimIds)->orderByDesc('updated_at')->limit(5)->get();

        return Inertia::render('Portal/Home', [
            'cases' => $cases->map(fn ($c) => Portal::caseSummary($c)),
            'executions' => $executions->map(fn ($e) => Portal::executionSummary($e)),
            'totals' => ['received' => $received['received'] ?? [], 'agreed' => $received['agreed'] ?? [], 'claimed' => $received['claimed'] ?? []],
            'claims' => $claims->map(fn ($c) => Portal::claim($c)),
            'canView' => $viewIds !== [],
            'canSubmit' => $claimIds !== [],
        ]);
    }

    public function showCase(Request $request, int $case)
    {
        $model = LegalCase::with(['filings', 'hearings', 'judgment', 'timeline', 'documents', 'collections'])
            ->whereIn('client_id', $request->user()->clientIdsFor('view'))
            ->where('work_status', '!=', 'draft')
            ->find($case);
        abort_unless($model, 404);

        return Inertia::render('Portal/CaseShow', ['case' => Portal::caseDetail($model)]);
    }

    public function showExecution(Request $request, int $execution)
    {
        $model = ExecutionRequest::with(['timeline', 'documents', 'collections'])
            ->whereIn('client_id', $request->user()->clientIdsFor('view'))
            ->where('status', '!=', 'draft')
            ->find($execution);
        abort_unless($model, 404);

        return Inertia::render('Portal/ExecutionShow', ['execution' => Portal::executionDetail($model)]);
    }
}
