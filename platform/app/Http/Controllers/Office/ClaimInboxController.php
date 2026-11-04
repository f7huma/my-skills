<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\CaseCategory;
use App\Models\Claim;
use App\Models\ClaimMessage;
use App\Models\ExecutionRequest;
use App\Services\Audit;
use App\Services\Workflow\ClaimService;
use App\Services\Workflow\ExecutionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClaimInboxController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $claims = Claim::with('client:id,name')->where('status', '!=', 'draft')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['under_review', 'needs_completion']))
            ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('submitted_at')->paginate(25)->withQueryString();

        return Inertia::render('Office/Claims/Index', [
            'claims' => $claims->through(fn (Claim $c) => ['id' => $c->id, 'reference' => $c->reference, 'client' => $c->client->name, 'opponent' => $c->opponent_name,
                'amount' => $c->amount, 'currency' => $c->currency, 'status' => $c->status, 'statusLabel' => Claim::STATUSES[$c->status],
                'missing' => $c->missing_documents, 'submittedAt' => $c->submitted_at?->toIso8601String()]),
            'status' => $status,
        ]);
    }

    public function show(Claim $claim, ClaimService $svc)
    {
        abort_if($claim->status === 'draft', 404);
        $claim->load(['client:id,name,type', 'submitter:id,name,email', 'messages.author:id,name', 'documents']);

        return Inertia::render('Office/Claims/Show', [
            'claim' => [
                'id' => $claim->id, 'reference' => $claim->reference, 'status' => $claim->status, 'statusLabel' => Claim::STATUSES[$claim->status],
                'client' => $claim->client->name, 'clientId' => $claim->client_id, 'clientName' => $claim->client_name,
                'submitter' => $claim->submitter?->name, 'opponentType' => $claim->opponent_type, 'opponentName' => $claim->opponent_name,
                'opponentIdentifier' => $claim->opponent_identifier, 'opponentAddress' => $claim->opponent_address, 'opponentPhone' => $claim->opponent_phone,
                'amount' => $claim->amount, 'currency' => $claim->currency, 'description' => $claim->description,
                'missingDocuments' => $claim->missing_documents, 'missingReason' => $claim->missing_document_reason, 'missingList' => $svc->missingRequired($claim),
                'submittedAt' => $claim->submitted_at?->toIso8601String(), 'internalNotes' => $claim->internal_notes, 'closedReason' => $claim->closed_reason,
                'caseId' => $claim->converted_case_id, 'executionId' => $claim->converted_execution_id,
            ],
            'messages' => $claim->messages->map(fn ($m) => ['id' => $m->id, 'kind' => $m->kind, 'body' => $m->body, 'visible' => $m->visible_to_client,
                'author' => $m->author?->name, 'at' => $m->created_at->toIso8601String()]),
            'documents' => $claim->documents->whereNull('deleted_at')->map(fn ($d) => Office::document($d))->values(),
            'categories' => CaseCategory::where('is_active', true)->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'instrumentTypes' => Office::options(ExecutionRequest::INSTRUMENT_TYPES),
            'categoryLabels' => \App\Models\Document::CATEGORIES,
        ]);
    }

    public function requestCompletion(Request $request, Claim $claim, ClaimService $svc)
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);
        abort_unless(in_array($claim->status, ['under_review', 'needs_completion'], true), 422);
        $svc->requestCompletion($claim, $data['message']);

        return back()->with('success', 'أُرسل طلب الاستكمال وسيظهر للعميل.');
    }

    public function note(Request $request, Claim $claim)
    {
        $data = $request->validate(['body' => 'required|string|max:3000', 'visible_to_client' => 'boolean']);
        ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => $request->user()->id, 'kind' => $request->boolean('visible_to_client') ? 'status' : 'internal_note',
            'body' => $data['body'], 'visible_to_client' => $request->boolean('visible_to_client')]);
        Audit::log('claim.note', $claim, ['visible' => $request->boolean('visible_to_client')]);

        return back();
    }

    public function internal(Request $request, Claim $claim)
    {
        $claim->forceFill($request->validate(['internal_notes' => 'nullable|string|max:5000']))->save();

        return back()->with('success', 'حُفظت البيانات الداخلية.');
    }

    public function convertCase(Request $request, Claim $claim, ClaimService $svc)
    {
        $data = $request->validate(['title' => 'nullable|string|max:250', 'category_id' => 'nullable|exists:case_categories,id', 'stage' => 'nullable|in:intake,reconciliation,filing']);
        $case = $svc->convertToCase($claim, $data);

        return redirect()->route('cases.show', $case)->with('success', 'حُوّلت المطالبة إلى القضية '.$case->reference.'.');
    }

    public function convertExecution(Request $request, Claim $claim, ClaimService $svc, ExecutionService $exec)
    {
        $data = $request->validate(['execution_type' => 'required|in:financial,direct', 'instrument_type' => 'required|in:judgment_deed,electronic_promissory_note,other',
            'instrument_label' => 'nullable|required_if:instrument_type,other|string|max:120',
            'subject_location' => 'nullable|required_if:execution_type,direct|string|max:250',
            'obligation_description' => 'nullable|required_if:execution_type,direct|string|max:3000']);
        $e = $svc->convertToExecution($claim, $exec, $data);

        return redirect()->route('executions.show', $e)->with('success', 'حُوّلت المطالبة إلى ملف التنفيذ '.$e->reference.'.');
    }

    public function close(Request $request, Claim $claim, ClaimService $svc)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000', 'client_message' => 'nullable|string|max:1000']);
        $svc->close($claim, $data['reason'], ! empty($data['client_message']), $data['client_message'] ?? null);

        return back()->with('success', 'أُغلقت المطالبة وحُفظ السبب.');
    }
}
