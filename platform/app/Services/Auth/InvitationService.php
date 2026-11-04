<?php

namespace App\Services\Auth;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Services\Audit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    /** @return array{invitation: Invitation, url: string} */
    public function create(array $data, ?int $invitedBy): array
    {
        // دعوة واحدة صالحة لكل بريد وعميل: تُلغى السابقة
        Invitation::where('email', $data['email'])->where('client_id', $data['client_id'] ?? null)->whereNull('accepted_at')->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
        $token = Str::random(48);
        $inv = Invitation::create([
            'email' => mb_strtolower($data['email']), 'name' => $data['name'], 'role' => $data['role'], 'purpose' => $data['purpose'] ?? 'invite',
            'client_id' => $data['client_id'] ?? null, 'can_view' => (bool) ($data['can_view'] ?? true),
            'can_submit_claims' => (bool) ($data['can_submit_claims'] ?? false),
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addHours(config('office.invitation_ttl_hours')), 'invited_by' => $invitedBy,
        ]);
        $url = route('invitation.show', $token);
        Mail::to($inv->email)->send(new InvitationMail($inv->name, $url, $inv->expires_at));
        Audit::log('invitation.created', $inv, ['role' => $inv->role, 'client' => $inv->client_id], $invitedBy);

        return ['invitation' => $inv, 'url' => $url];
    }
}
