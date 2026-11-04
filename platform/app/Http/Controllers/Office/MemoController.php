<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Office;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Memo;
use App\Models\MemoVersion;
use App\Services\Audit;
use App\Services\HtmlSanitizer;
use App\Services\Timeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * المذكرات: محرر داخلي فقط. لا تصل المسودات إلى بوابة العميل ولا لأي خدمة خارجية.
 * كل حفظ ينشئ إصدارًا جديدًا. تاريخ الإيداع منفصل عن تاريخ الاعتماد الداخلي.
 */
class MemoController extends Controller
{
    public function store(Request $request, LegalCase $case)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Memo::TYPES))], 'title' => 'required|string|max:200', 'due_on' => 'nullable|date',
            'hearing_id' => ['nullable', Rule::exists('hearings', 'id')->where('legal_case_id', $case->id)],
        ]);
        $memo = $case->memos()->create($data + ['status' => 'draft']);
        Audit::log('memo.created', $memo);

        return redirect()->route('memos.edit', $memo);
    }

    public function edit(Memo $memo)
    {
        $memo->load(['legalCase:id,reference,title,client_id', 'legalCase.client:id,name', 'filedDocument']);
        $latest = $memo->latestVersion();

        return Inertia::render('Office/Memos/Edit', [
            'memo' => [
                'id' => $memo->id, 'title' => $memo->title, 'type' => $memo->type, 'typeLabel' => Memo::TYPES[$memo->type], 'status' => $memo->status,
                'dueOn' => $memo->due_on?->toDateString(), 'approvedAt' => $memo->approved_at?->toIso8601String(), 'filedOn' => $memo->filed_on?->toDateString(),
                'hearingId' => $memo->hearing_id, 'version' => $memo->current_version, 'lockVersion' => $memo->lock_version,
                'body' => $latest?->body_html ?? '', 'filedDocument' => $memo->filedDocument ? Office::document($memo->filedDocument) : null,
            ],
            'case' => ['id' => $memo->legalCase->id, 'reference' => $memo->legalCase->reference, 'title' => $memo->legalCase->title, 'client' => $memo->legalCase->client->name, 'clientId' => $memo->legalCase->client_id],
            'versions' => $memo->versions()->limit(30)->get(['id', 'memo_id', 'version', 'created_at', 'created_by'])
                ->map(fn ($v) => ['id' => $v->id, 'version' => $v->version, 'at' => $v->created_at->toIso8601String()]),
            'hearings' => Hearing::where('legal_case_id', $memo->legal_case_id)->orderBy('scheduled_at')->get()
                ->map(fn ($h) => ['value' => $h->id, 'label' => Hearing::KINDS[$h->kind].' — '.local_date($h->scheduled_at)]),
            'statuses' => Office::options(Memo::STATUSES),
            'filedCandidates' => \App\Models\Document::alive()->where('client_id', $memo->legalCase->client_id)->where('category', 'memo_filed')->where('scan_status', 'clean')
                ->latest()->limit(20)->get()->map(fn ($d) => ['value' => $d->uuid, 'label' => $d->label ?: $d->original_name]),
        ]);
    }

    public function update(Request $request, Memo $memo)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200', 'body' => 'nullable|string|max:500000', 'due_on' => 'nullable|date',
            'hearing_id' => ['nullable', Rule::exists('hearings', 'id')->where('legal_case_id', $memo->legal_case_id)],
            'lock_version' => 'required|integer',
        ]);
        DB::transaction(function () use ($memo, $data, $request) {
            $body = HtmlSanitizer::clean((string) ($data['body'] ?? ''));
            $memo->fill(['title' => $data['title'], 'due_on' => $data['due_on'] ?? null, 'hearing_id' => $data['hearing_id'] ?? null]);
            $latest = $memo->latestVersion();
            if (! $latest || $latest->body_html !== $body) {
                $memo->current_version = $memo->current_version + 1;
                MemoVersion::create(['memo_id' => $memo->id, 'version' => $memo->current_version, 'body_html' => $body, 'created_by' => $request->user()->id, 'created_at' => now()]);
            }
            $memo->lock_version = $memo->lock_version; // touch
            $memo->saveWithVersion((int) $data['lock_version']);
            Audit::log('memo.saved', $memo, ['version' => $memo->current_version]);
        });

        return back()->with('success', 'حُفظت المسودة (الإصدار '.$memo->current_version.').');
    }

    public function status(Request $request, Memo $memo)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Memo::STATUSES))], 'filed_on' => 'nullable|required_if:status,filed|date',
            'filed_document' => 'nullable|string', 'lock_version' => 'required|integer',
        ], ['filed_on.required_if' => 'أدخل تاريخ الإيداع.']);
        $memo->status = $data['status'];
        if ($data['status'] === 'approved' && ! $memo->approved_at) {
            $memo->approved_at = now();
        }
        if ($data['status'] === 'filed') {
            $memo->filed_on = $data['filed_on'];
            $memo->approved_at ??= now();
            if (! empty($data['filed_document'])) {
                $memo->filed_document_id = ProceedingsController::docId($data['filed_document'], $memo->legalCase->client_id);
            }
        }
        $memo->saveWithVersion((int) $data['lock_version']);
        if ($data['status'] === 'filed') {
            Timeline::record($memo->legalCase, 'memo.filed', 'إيداع '.Memo::TYPES[$memo->type], 'تاريخ الإيداع: '.$memo->filed_on->toDateString(), $memo, true);
        }
        Audit::log('memo.status', $memo, ['status' => $memo->status]);

        return back()->with('success', 'حُدّثت حالة المذكرة.');
    }

    public function version(Memo $memo, int $version)
    {
        $v = $memo->versions()->where('version', $version)->firstOrFail();

        return response()->json(['version' => $v->version, 'body' => $v->body_html, 'at' => $v->created_at->toIso8601String()]);
    }

    public function print(Memo $memo)
    {
        $memo->load('legalCase.client');
        Audit::log('memo.printed', $memo, ['version' => $memo->current_version]);

        return response()->view('print.memo', [
            'memo' => $memo, 'body' => $memo->latestVersion()?->body_html ?? '', 'typeLabel' => Memo::TYPES[$memo->type],
        ])->header('Cache-Control', 'no-store, private');
    }
}
