<?php

namespace App\Services\Workflow;

use App\Models\Claim;
use App\Models\ClaimMessage;
use App\Models\Collection;
use App\Models\Document;
use App\Models\LegalCase;
use App\Services\Audit;
use App\Services\Timeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClaimService
{
    /** المستندات الأساسية بحسب نوع الطرف المطالب ضده */
    public static function requiredCategories(?string $opponentType): array
    {
        $req = ['invoice', 'statement'];
        if ($opponentType === 'organization') {
            $req[] = 'commercial_registration';
        }

        return $req;
    }

    public function missingRequired(Claim $claim): array
    {
        $have = $claim->documents()->alive()->whereNotIn('scan_status', ['infected', 'rejected'])->pluck('category')->all();

        return array_values(array_diff(self::requiredCategories($claim->opponent_type), $have));
    }

    /**
     * الإرسال: مفتاح إرسال فريد يمنع الازدواج عند الضغط مرتين أو إعادة المحاولة.
     */
    public function submit(Claim $claim, string $submitKey): Claim
    {
        return DB::transaction(function () use ($claim, $submitKey) {
            $dup = Claim::where('submit_key', $submitKey)->where('id', '!=', $claim->id)->first();
            if ($dup) {
                return $dup;
            }
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->first();
            if ($claim->submit_key === $submitKey && $claim->status === 'under_review') {
                return $claim; // إعادة محاولة لنفس الإرسال
            }
            if (! $claim->isEditableByClient()) {
                throw ValidationException::withMessages(['claim' => 'لا يمكن إرسال المطالبة في حالتها الحالية.']);
            }
            foreach (['opponent_type' => 'نوع الطرف المطالب ضده', 'opponent_name' => 'اسم الطرف المطالب ضده', 'opponent_identifier' => 'رقم السجل أو الهوية', 'amount' => 'مبلغ المطالبة'] as $f => $label) {
                if (blank($claim->{$f})) {
                    throw ValidationException::withMessages([$f => "أكمل حقل {$label} قبل الإرسال."]);
                }
            }
            $missing = $this->missingRequired($claim);
            if ($missing && blank($claim->missing_document_reason)) {
                throw ValidationException::withMessages(['missing_document_reason' => 'وضّح سبب عدم توفر: '.implode('، ', array_map(fn ($c) => Document::CATEGORIES[$c], $missing)).'، أو أرفقها.']);
            }
            $first = $claim->status === 'draft';
            $claim->missing_documents = (bool) $missing;
            $claim->status = 'under_review';
            $claim->submit_key = $submitKey;
            $claim->submitted_at ??= now();
            if (! $claim->reference) {
                $claim->reference = \App\Models\References::next('CLM', 'claims');
            }
            $claim->save();
            ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => auth()->id(), 'kind' => 'status',
                'body' => $first ? 'أُرسلت المطالبة وهي قيد الدراسة.' : 'أُعيد إرسال المطالبة بعد الاستكمال.', 'visible_to_client' => true]);
            Audit::log('claim.submitted', $claim, ['missing_documents' => $claim->missing_documents]);

            return $claim;
        });
    }

    public function requestCompletion(Claim $claim, string $message): void
    {
        $claim->forceFill(['status' => 'needs_completion'])->save();
        ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => auth()->id(), 'kind' => 'completion_request', 'body' => $message, 'visible_to_client' => true]);
        Audit::log('claim.completion_requested', $claim);
    }

    public function close(Claim $claim, string $reason, bool $notifyClient, ?string $clientMessage): void
    {
        $claim->forceFill(['status' => 'closed', 'closed_reason' => $reason])->save();
        if ($notifyClient && $clientMessage) {
            ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => auth()->id(), 'kind' => 'status', 'body' => $clientMessage, 'visible_to_client' => true]);
        }
        Audit::log('claim.closed', $claim);
    }

    /** التحويل إلى قضية — آمن من التكرار عبر القفل والقيد الفريد source_claim_id */
    public function convertToCase(Claim $claim, array $extra = []): LegalCase
    {
        return DB::transaction(function () use ($claim, $extra) {
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->first();
            if ($claim->converted_case_id) {
                return $claim->convertedCase;
            }
            if (! in_array($claim->status, ['under_review', 'needs_completion'], true)) {
                throw ValidationException::withMessages(['claim' => 'لا يمكن تحويل المطالبة في حالتها الحالية.']);
            }
            $case = LegalCase::create([
                'client_id' => $claim->client_id,
                'title' => $extra['title'] ?? ('مطالبة مالية ضد '.$claim->opponent_name),
                'category_id' => $extra['category_id'] ?? null,
                'client_role' => 'plaintiff',
                'stage' => $extra['stage'] ?? 'intake',
                'work_status' => 'active',
                'claim_amount' => $claim->amount,
                'currency' => $claim->currency,
                'source_claim_id' => $claim->id,
            ]);
            $case->parties()->create(['name' => $claim->opponent_name, 'role' => 'defendant', 'party_type' => $claim->opponent_type ?? 'individual', 'identifier' => $claim->opponent_identifier]);
            $this->linkDocuments($claim, $case);
            if ($claim->amount) {
                Collection::create(['client_id' => $case->client_id, 'legal_case_id' => $case->id, 'kind' => 'claimed', 'source' => 'other',
                    'amount' => $claim->amount, 'currency' => $claim->currency, 'occurred_on' => today_local(), 'entry_key' => (string) Str::uuid(),
                    'created_by' => auth()->id(), 'description' => 'مبلغ المطالبة الأصلية '.$claim->reference]);
            }
            $claim->forceFill(['status' => 'converted_case', 'converted_case_id' => $case->id])->save();
            ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => auth()->id(), 'kind' => 'status', 'body' => 'قُبلت المطالبة وفُتح لها ملف قضية لدى المكتب.', 'visible_to_client' => true]);
            Timeline::record($case, 'case.created_from_claim', 'فتح القضية من المطالبة '.$claim->reference, null, $claim, true);
            Audit::log('claim.converted_case', $claim, ['case' => $case->id]);

            return $case;
        });
    }

    public function convertToExecution(Claim $claim, ExecutionService $exec, array $data): \App\Models\ExecutionRequest
    {
        return DB::transaction(function () use ($claim, $exec, $data) {
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->first();
            if ($claim->converted_execution_id) {
                return $claim->convertedExecution;
            }
            if (! in_array($claim->status, ['under_review', 'needs_completion'], true)) {
                throw ValidationException::withMessages(['claim' => 'لا يمكن تحويل المطالبة في حالتها الحالية.']);
            }
            $e = $exec->create($data + [
                'client_id' => $claim->client_id,
                'amount_claimed' => $claim->amount,
                'currency' => $claim->currency,
                'status' => 'draft',
                'parties' => [['name' => $claim->opponent_name, 'role' => 'respondent', 'party_type' => $claim->opponent_type ?? 'individual', 'identifier' => $claim->opponent_identifier]],
            ]);
            $e->forceFill(['source_claim_id' => $claim->id])->save();
            $this->linkDocuments($claim, $e);
            $claim->forceFill(['status' => 'converted_execution', 'converted_execution_id' => $e->id])->save();
            ClaimMessage::create(['claim_id' => $claim->id, 'author_id' => auth()->id(), 'kind' => 'status', 'body' => 'قُبلت المطالبة وفُتح لها ملف تنفيذ لدى المكتب.', 'visible_to_client' => true]);
            Audit::log('claim.converted_execution', $claim, ['execution' => $e->id]);

            return $e;
        });
    }

    /** المرفقات تبقى مرتبطة بالمطالبة الأصلية؛ ونسجّل ارتباطها بالسجل الناتج عبر الحدث الزمني والمرجع */
    private function linkDocuments(Claim $claim, $target): void
    {
        $count = $claim->documents()->alive()->count();
        if ($count) {
            Timeline::record($target, 'claim.documents', "مرفقات المطالبة الأصلية ({$count})", 'تبقى المرفقات محفوظة في سجل المطالبة '.$claim->reference.' وتظهر في تبويب المرفقات.', $claim, false);
        }
    }
}
