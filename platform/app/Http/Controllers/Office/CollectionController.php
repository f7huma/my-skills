<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Collection;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public static function present(Collection $c): array
    {
        return [
            'id' => $c->id, 'kind' => $c->kind, 'kindLabel' => Collection::KINDS[$c->kind], 'source' => $c->source, 'sourceLabel' => Collection::SOURCES[$c->source],
            'amount' => $c->amount, 'currency' => $c->currency, 'on' => $c->occurred_on->toDateString(), 'description' => $c->description,
            'visible' => $c->visible_to_client, 'executionId' => $c->execution_request_id, 'reconciliationId' => $c->reconciliation_request_id,
            'document' => $c->relationLoaded('document') && $c->document ? Office::document($c->document) : null,
        ];
    }

    /**
     * عملية مستقلة بمفتاح فريد يرسله النموذج: إعادة الإرسال لا تكرر الدفعة.
     * الدفعة المقبوضة في ملف تنفيذ مرتبط بقضية تُسجّل مرة واحدة وتظهر في القضية والتنفيذ معًا.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_key' => 'required|uuid',
            'legal_case_id' => 'nullable|exists:legal_cases,id',
            'execution_request_id' => 'nullable|exists:execution_requests,id',
            'reconciliation_request_id' => 'nullable|exists:reconciliation_requests,id',
            'kind' => ['required', Rule::in(array_keys(Collection::KINDS))],
            'source' => ['required', Rule::in(array_keys(Collection::SOURCES))],
            'amount' => 'required|numeric|gt:0|max:9999999999999999',
            'currency' => ['required', Rule::in(config('office.currencies'))],
            'occurred_on' => 'required|date',
            'description' => 'nullable|string|max:250',
            'visible_to_client' => 'boolean',
            'document' => 'nullable|string',
        ]);
        if ($existing = Collection::where('entry_key', $data['entry_key'])->first()) {
            return back()->with('success', 'العملية مسجلة مسبقًا.');
        }
        $exec = ! empty($data['execution_request_id']) ? ExecutionRequest::find($data['execution_request_id']) : null;
        $case = ! empty($data['legal_case_id']) ? LegalCase::find($data['legal_case_id']) : ($exec?->legal_case_id ? LegalCase::find($exec->legal_case_id) : null);
        abort_unless($case || $exec, 422);
        $clientId = $case?->client_id ?? $exec->client_id;
        abort_if($exec && $case && $exec->client_id !== $case->client_id, 422);
        $c = Collection::create([
            'client_id' => $clientId, 'legal_case_id' => $case?->id, 'execution_request_id' => $exec?->id,
            'reconciliation_request_id' => $data['reconciliation_request_id'] ?? null,
            'kind' => $data['kind'], 'source' => $data['source'], 'amount' => $data['amount'], 'currency' => $data['currency'],
            'occurred_on' => $data['occurred_on'], 'description' => $data['description'] ?? null, 'visible_to_client' => $request->boolean('visible_to_client', true),
            'document_id' => ProceedingsController::docId($data['document'] ?? null, $clientId),
            'entry_key' => $data['entry_key'], 'created_by' => $request->user()->id,
        ]);
        Audit::log('collection.recorded', $c, ['kind' => $c->kind, 'source' => $c->source, 'currency' => $c->currency]);

        return back()->with('success', 'سُجّلت العملية المالية.');
    }

    public function destroy(Request $request, Collection $collection)
    {
        $request->validate(['reason' => 'required|string|max:250']);
        Audit::log('collection.deleted', $collection, ['kind' => $collection->kind, 'reason' => $request->input('reason')]);
        $collection->delete();

        return back()->with('success', 'حُذفت العملية وسُجّل السبب.');
    }
}
