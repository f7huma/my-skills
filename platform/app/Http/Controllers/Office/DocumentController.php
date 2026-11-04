<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Document;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Files\FileVault;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function store(Request $request, FileVault $vault)
    {
        $data = $request->validate([
            'owner_type' => 'required|in:client,case,execution,claim',
            'owner_id' => 'required|integer',
            'file' => 'required|file',
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'party' => ['nullable', Rule::in(array_keys(Document::PARTIES))],
            'label' => 'nullable|string|max:120',
            'shared_with_client' => 'boolean',
        ]);
        [$owner, $clientId] = match ($data['owner_type']) {
            'client' => [null, Client::findOrFail($data['owner_id'])->id],
            'case' => [$c = LegalCase::findOrFail($data['owner_id']), $c->client_id],
            'execution' => [$e = ExecutionRequest::findOrFail($data['owner_id']), $e->client_id],
            'claim' => [$cl = Claim::findOrFail($data['owner_id']), $cl->client_id],
        };
        $doc = $vault->store($request->file('file'), [
            'client_id' => $clientId, 'category' => $data['category'], 'party' => $data['party'] ?? 'office',
            'label' => $data['label'] ?? null, 'shared_with_client' => $request->boolean('shared_with_client'),
        ], $owner);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['document' => Office::document($doc)]);
        }

        return back()->with('success', 'رُفع الملف وهو قيد الفحص الأمني قبل إتاحته.');
    }

    public function share(Request $request, Document $document)
    {
        $data = $request->validate(['shared' => 'required|boolean']);
        abort_if($document->deleted_at, 404);
        $document->forceFill([
            'shared_with_client' => $data['shared'],
            'shared_at' => $data['shared'] ? now() : null,
            'shared_by' => $data['shared'] ? $request->user()->id : null,
        ])->save();
        Audit::log($data['shared'] ? 'file.shared' : 'file.unshared', $document);

        return back()->with('success', $data['shared'] ? 'أصبح المستند ظاهرًا في بوابة العميل.' : 'أُخفي المستند من بوابة العميل.');
    }

    public function destroy(Request $request, Document $document, FileVault $vault)
    {
        $data = $request->validate(['reason' => 'required|string|max:250']);
        abort_unless($request->user()->can('delete', $document), 404);
        $vault->softDelete($document, $data['reason']);

        return back()->with('success', 'نُقل المستند إلى المحذوفات (يُحتفظ به حتى انتهاء مدة الاحتفاظ).');
    }

    public function rescan(Document $document)
    {
        abort_unless(in_array($document->scan_status, ['error', 'pending'], true), 422);
        $document->forceFill(['scan_status' => 'pending'])->save();
        \App\Jobs\ScanDocument::dispatch($document->id);

        return back()->with('success', 'أُعيدت جدولة الفحص.');
    }
}
