<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Document;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Models\PowerOfAttorney;
use App\Models\Setting;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $clients = Client::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', "%{$q}%")->orWhere('reference', 'ilike', "%{$q}%")
                ->orWhere('national_id', 'ilike', "%{$q}%")->orWhere('unified_number', 'ilike', "%{$q}%")->orWhere('commercial_registration', 'ilike', "%{$q}%")))
            ->when(in_array($type, ['individual', 'organization'], true), fn ($w) => $w->where('type', $type))
            ->when(! $request->boolean('archived'), fn ($w) => $w->whereNull('archived_at'))
            ->withCount(['cases', 'cases as active_count' => fn ($x) => $x->whereIn('work_status', LegalCase::ACTIVE_STATUSES)])
            ->orderBy('name')->paginate(25)->withQueryString();

        return Inertia::render('Office/Clients/Index', [
            'clients' => $clients->through(fn ($c) => ['id' => $c->id, 'reference' => $c->reference, 'name' => $c->name, 'type' => $c->type,
                'typeLabel' => Client::TYPES[$c->type], 'phone' => $c->phone, 'cases' => $c->cases_count, 'active' => $c->active_count,
                'archived' => (bool) $c->archived_at]),
            'filters' => ['q' => $q, 'type' => $type, 'archived' => $request->boolean('archived')],
        ]);
    }

    public function create()
    {
        return Inertia::render('Office/Clients/Form', ['client' => null]);
    }

    private function rules(?Client $client = null): array
    {
        return [
            'type' => 'required|in:individual,organization',
            'name' => 'required|string|max:200',
            'national_id' => ['nullable', 'regex:/^[12][0-9]{9}$/'],
            'unified_number' => ['nullable', 'regex:/^7[0-9]{9}$/'],
            'commercial_registration' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'address' => 'nullable|string|max:500',
            'national_address' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:190',
            'internal_notes' => 'nullable|string|max:5000',
        ];
    }

    private function messages(): array
    {
        return [
            'national_id.regex' => 'رقم الهوية أو الإقامة يتكون من 10 أرقام ويبدأ بـ 1 أو 2.',
            'unified_number.regex' => 'الرقم الوطني الموحد يتكون من 10 أرقام ويبدأ بـ 7.',
            'commercial_registration.regex' => 'رقم السجل التجاري يتكون من 10 أرقام.',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), $this->messages());
        $client = Client::create($data + ['is_synthetic' => (bool) config('office.synthetic_only')]);
        Audit::log('client.created', $client);

        return redirect()->route('clients.show', $client)->with('success', 'أُضيف العميل.');
    }

    public function edit(Client $client)
    {
        return Inertia::render('Office/Clients/Form', ['client' => $client->only(['id', 'type', 'name', 'national_id', 'unified_number', 'commercial_registration', 'address', 'national_address', 'phone', 'email', 'internal_notes'])]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate($this->rules($client), $this->messages());
        $client->update($data);
        Audit::log('client.updated', $client, ['fields' => array_keys($client->getChanges())]);

        return redirect()->route('clients.show', $client)->with('success', 'حُفظت بيانات العميل.');
    }

    public function archive(Request $request, Client $client)
    {
        $client->forceFill(['archived_at' => $client->archived_at ? null : now()])->save();
        Audit::log($client->archived_at ? 'client.archived' : 'client.unarchived', $client);

        return back();
    }

    public function show(Request $request, Client $client)
    {
        $warn = (int) Setting::get('poa_expiry_warning_days', config('office.poa_expiry_warning_days'));
        $docs = Document::where('client_id', $client->id)->alive()->orderByDesc('created_at')->get();

        return Inertia::render('Office/Clients/Show', [
            'tab' => $request->query('tab', 'overview'),
            'client' => [
                'id' => $client->id, 'reference' => $client->reference, 'type' => $client->type, 'typeLabel' => Client::TYPES[$client->type],
                'name' => $client->name, 'nationalId' => $client->national_id, 'unifiedNumber' => $client->unified_number,
                'commercialRegistration' => $client->commercial_registration, 'address' => $client->address, 'nationalAddress' => $client->national_address,
                'phone' => $client->phone, 'email' => $client->email, 'internalNotes' => $client->internal_notes, 'archived' => (bool) $client->archived_at,
            ],
            'counts' => $client->caseCounts(),
            'poas' => $client->powersOfAttorney()->with('document')->orderByRaw("case when status = 'revoked' then 1 else 0 end")->orderBy('expires_on')->get()
                ->map(fn (PowerOfAttorney $p) => ['id' => $p->id, 'number' => $p->number, 'issued' => $p->issued_on?->toDateString(), 'expires' => $p->expires_on?->toDateString(),
                    'state' => $p->effectiveStatus($warn), 'notes' => $p->notes, 'document' => $p->document ? Office::document($p->document) : null]),
            'cases' => $client->cases()->with('category:id,name')->orderByDesc('updated_at')->get()->map(fn (LegalCase $c) => [
                'id' => $c->id, 'reference' => $c->reference, 'title' => $c->title, 'stage' => LegalCase::STAGES[$c->stage], 'status' => $c->work_status,
                'statusLabel' => LegalCase::WORK_STATUSES[$c->work_status], 'category' => $c->category?->name, 'role' => LegalCase::CLIENT_ROLES[$c->client_role]]),
            'executions' => $client->executionRequests()->orderByDesc('updated_at')->get()->map(fn (ExecutionRequest $e) => [
                'id' => $e->id, 'reference' => $e->reference, 'requestNumber' => $e->request_number, 'type' => ExecutionRequest::EXECUTION_TYPES[$e->execution_type],
                'status' => ExecutionRequest::STATUSES[$e->status] ?? $e->status, 'caseId' => $e->legal_case_id]),
            'claims' => $client->claims()->where('status', '!=', 'draft')->orderByDesc('submitted_at')->get()->map(fn (Claim $c) => [
                'id' => $c->id, 'reference' => $c->reference, 'opponent' => $c->opponent_name, 'status' => $c->status, 'statusLabel' => Claim::STATUSES[$c->status],
                'amount' => $c->amount, 'currency' => $c->currency]),
            'documents' => $docs->where('documentable_type', null)->map(fn ($d) => Office::document($d))->values(),
            'allDocumentsCount' => $docs->count(),
            'totals' => Collection::totals(Collection::where('client_id', $client->id)),
            'members' => $client->memberships()->with('user:id,name,email,is_active,last_login_at')->get()->map(fn ($m) => [
                'id' => $m->id, 'userId' => $m->user_id, 'name' => $m->user->name, 'email' => $m->user->email, 'active' => $m->user->is_active,
                'canView' => $m->can_view, 'canSubmitClaims' => $m->can_submit_claims, 'revoked' => (bool) $m->revoked_at,
                'lastLogin' => $m->user->last_login_at?->toIso8601String()]),
            'pendingInvitations' => \App\Models\Invitation::where('client_id', $client->id)->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->get(['id', 'name', 'email', 'expires_at', 'can_view', 'can_submit_claims']),
            'categories' => Office::options(collect(Document::CATEGORIES)->only(['fee_contract', 'poa', 'identity', 'commercial_registration', 'articles', 'national_address', 'other'])->all()),
        ]);
    }

    // ---------------------------------------------------------------- الوكالات
    public function storePoa(Request $request, Client $client)
    {
        $data = $request->validate([
            'number' => 'required|string|max:60',
            'issued_on' => 'nullable|date',
            'expires_on' => 'nullable|date|after_or_equal:issued_on',
            'notes' => 'nullable|string|max:1000',
            'document_id' => ['nullable', 'string', Rule::exists('documents', 'uuid')->where('client_id', $client->id)],
        ]);
        $docId = ! empty($data['document_id']) ? Document::where('uuid', $data['document_id'])->value('id') : null;
        // وكالة جديدة لا تستبدل القديمة: سجل مستقل
        $poa = $client->powersOfAttorney()->create(['number' => $data['number'], 'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null, 'notes' => $data['notes'] ?? null, 'document_id' => $docId, 'status' => 'active']);
        Audit::log('poa.created', $poa);

        return back()->with('success', 'سُجّلت الوكالة.');
    }

    public function updatePoa(Request $request, Client $client, PowerOfAttorney $poa)
    {
        abort_unless($poa->client_id === $client->id, 404);
        $data = $request->validate([
            'number' => 'required|string|max:60',
            'issued_on' => 'nullable|date',
            'expires_on' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'status' => 'required|in:active,revoked',
            'revoked_on' => 'nullable|date',
        ]);
        if ($data['status'] === 'revoked') {
            $data['revoked_on'] ??= today_local()->toDateString();
        }
        $poa->update($data);
        Audit::log('poa.updated', $poa, ['status' => $poa->status]);

        return back()->with('success', 'حُدّثت الوكالة.');
    }
}
