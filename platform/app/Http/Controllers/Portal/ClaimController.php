<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Http\Presenters\Portal;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Document;
use App\Services\Audit;
use App\Services\Files\FileVault;
use App\Services\Workflow\ClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClaimController extends Controller
{
    private function find(Request $request, int $id): Claim
    {
        $claim = Claim::whereIn('client_id', $request->user()->clientIdsFor('claims'))->find($id);
        abort_unless($claim, 404);

        return $claim;
    }

    public function index(Request $request)
    {
        $claims = Claim::whereIn('client_id', $request->user()->clientIdsFor('claims'))->orderByDesc('updated_at')->get();

        return Inertia::render('Portal/Claims/Index', ['claims' => $claims->map(fn ($c) => Portal::claim($c))]);
    }

    /** إنشاء مسودة: بيانات صاحب الحساب تُملأ تلقائيًا من العضوية */
    public function store(Request $request)
    {
        $ids = $request->user()->clientIdsFor('claims');
        abort_if($ids === [], 403);
        $data = $request->validate(['client_id' => ['nullable', 'integer', Rule::in($ids)]]);
        $client = Client::findOrFail($data['client_id'] ?? $ids[0]);
        $claim = Claim::create(['client_id' => $client->id, 'submitted_by' => $request->user()->id, 'client_name' => $client->name, 'status' => 'draft', 'currency' => 'SAR']);
        Audit::log('claim.draft_created', $claim);

        return redirect()->route('portal.claims.edit', $claim->id);
    }

    public function edit(Request $request, int $claim)
    {
        $model = $this->find($request, $claim)->load(['messages', 'documents']);

        return Inertia::render('Portal/Claims/Edit', [
            'claim' => Portal::claim($model, true),
            'submitKey' => (string) Str::uuid(),
            'requiredByType' => ['individual' => ClaimService::requiredCategories('individual'), 'organization' => ClaimService::requiredCategories('organization')],
            'categories' => Office::options(collect(Document::CATEGORIES)->only(['invoice', 'statement', 'commercial_registration', 'promissory_note', 'balance_confirmation', 'agreement', 'identity', 'other'])->all()),
            'parties' => Office::options(collect(Document::PARTIES)->only(['client', 'opponent'])->all()),
            'currencies' => config('office.currencies'),
        ]);
    }

    public function update(Request $request, int $claim)
    {
        $model = $this->find($request, $claim);
        abort_unless($model->isEditableByClient(), 403);
        $data = $request->validate([
            'opponent_type' => 'nullable|in:individual,organization',
            'opponent_name' => 'nullable|string|max:200',
            'opponent_identifier' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'opponent_address' => 'nullable|string|max:300',
            'opponent_phone' => 'nullable|string|max:30',
            'amount' => 'nullable|numeric|min:0|max:9999999999999999',
            'currency' => ['required', Rule::in(config('office.currencies'))],
            'description' => 'nullable|string|max:5000',
            'missing_document_reason' => 'nullable|string|max:2000',
            'lock_version' => 'required|integer',
        ], ['opponent_identifier.regex' => 'رقم السجل أو الهوية يتكون من أرقام فقط.']);
        $model->fill(collect($data)->except('lock_version')->all());
        $model->saveWithVersion((int) $data['lock_version']);

        return back()->with('success', 'حُفظت المسودة.');
    }

    public function upload(Request $request, int $claim, FileVault $vault)
    {
        $model = $this->find($request, $claim);
        abort_unless($model->isEditableByClient(), 403);
        $data = $request->validate([
            'file' => 'required|file',
            'category' => ['required', Rule::in(['invoice', 'statement', 'commercial_registration', 'promissory_note', 'balance_confirmation', 'agreement', 'identity', 'other'])],
            'party' => 'required|in:client,opponent',
            'label' => 'nullable|string|max:120',
        ]);
        $vault->store($request->file('file'), ['client_id' => $model->client_id, 'category' => $data['category'], 'party' => $data['party'], 'label' => $data['label'] ?? null], $model);

        return back()->with('success', 'رُفع الملف وهو قيد الفحص الأمني.');
    }

    public function deleteDocument(Request $request, int $claim, string $uuid, FileVault $vault)
    {
        $model = $this->find($request, $claim);
        $doc = $model->documents()->where('uuid', $uuid)->first();
        abort_unless($doc && $request->user()->can('delete', $doc), 404);
        $vault->softDelete($doc, 'حذفه العميل قبل الإرسال');

        return back();
    }

    public function submit(Request $request, int $claim, ClaimService $service)
    {
        $model = $this->find($request, $claim);
        $data = $request->validate(['submit_key' => 'required|uuid']);
        $result = $service->submit($model, $data['submit_key']);

        return redirect()->route('portal.claims.show', $result->id)->with('success', 'أُرسلت المطالبة برقم '.$result->reference.'، وهي الآن قيد الدراسة.');
    }

    public function show(Request $request, int $claim)
    {
        $model = $this->find($request, $claim)->load(['messages', 'documents']);
        if ($model->status === 'draft') {
            return redirect()->route('portal.claims.edit', $model->id);
        }

        return Inertia::render('Portal/Claims/Show', ['claim' => Portal::claim($model, true)]);
    }
}
