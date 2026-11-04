<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Client;
use App\Models\CourtFiling;
use App\Models\ExecutionRequest;
use App\Models\LegalCase;
use App\Models\PowerOfAttorney;
use App\Models\User;

/**
 * البحث الداخلي ضمن صلاحية المستخدم. للمحامي فقط (بوابة العميل لها بحثها المقيّد بالعضوية).
 */
class SearchService
{
    public function search(User $user, string $q, int $limit = 8): array
    {
        abort_unless($user->isLawyer(), 403);
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $out = [];

        foreach (Client::where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('reference', 'ilike', $like)
            ->orWhere('national_id', 'ilike', $like)->orWhere('unified_number', 'ilike', $like)->orWhere('commercial_registration', 'ilike', $like))
            ->limit($limit)->get() as $c) {
            $out[] = ['type' => 'عميل', 'title' => $c->name, 'sub' => $c->reference, 'href' => route('clients.show', $c)];
        }
        $caseIds = CourtFiling::where('case_number', 'ilike', $like)->orWhere('filing_request_number', 'ilike', $like)->pluck('legal_case_id')
            ->merge(\App\Models\ReconciliationRequest::where('request_number', 'ilike', $like)->pluck('legal_case_id'))
            ->merge(\App\Models\CaseParty::where('name', 'ilike', $like)->orWhere('identifier', 'ilike', $like)->pluck('legal_case_id'));
        foreach (LegalCase::with('client:id,name')->where(fn ($w) => $w->where('reference', 'ilike', $like)->orWhere('title', 'ilike', $like)->orWhereIn('id', $caseIds))
            ->limit($limit)->get() as $c) {
            $out[] = ['type' => 'قضية', 'title' => $c->title, 'sub' => $c->reference.' · '.$c->client->name, 'href' => route('cases.show', $c)];
        }
        $exIds = \App\Models\ExecutionParty::where('name', 'ilike', $like)->orWhere('identifier', 'ilike', $like)->pluck('execution_request_id');
        foreach (ExecutionRequest::with('client:id,name')->where(fn ($w) => $w->where('reference', 'ilike', $like)->orWhere('request_number', 'ilike', $like)
            ->orWhere('note_number', 'ilike', $like)->orWhere('deed_number', 'ilike', $like)->orWhereIn('id', $exIds))->limit($limit)->get() as $e) {
            $out[] = ['type' => 'تنفيذ', 'title' => $e->request_number ? 'طلب تنفيذ '.$e->request_number : 'طلب تنفيذ', 'sub' => $e->reference.' · '.$e->client->name, 'href' => route('executions.show', $e)];
        }
        foreach (PowerOfAttorney::with('client:id,name')->where('number', 'ilike', $like)->limit($limit)->get() as $p) {
            $out[] = ['type' => 'وكالة', 'title' => 'وكالة '.$p->number, 'sub' => $p->client->name, 'href' => route('clients.show', ['client' => $p->client_id, 'tab' => 'poa'])];
        }
        foreach (Claim::with('client:id,name')->whereNotNull('reference')->where(fn ($w) => $w->where('reference', 'ilike', $like)->orWhere('opponent_name', 'ilike', $like))
            ->limit($limit)->get() as $c) {
            $out[] = ['type' => 'مطالبة', 'title' => $c->reference.' — '.$c->opponent_name, 'sub' => $c->client->name, 'href' => route('claims.show', $c)];
        }

        return $out;
    }
}
