<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Audit;
use App\Services\Auth\AccountManager;
use App\Services\Auth\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users', [
            'users' => User::with(['memberships.client:id,name'])->orderBy('role')->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'username' => $u->username, 'role' => $u->role, 'active' => $u->is_active,
                'twoFactor' => $u->hasTwoFactor(), 'lastLogin' => $u->last_login_at?->toIso8601String(),
                'memberships' => $u->memberships->map(fn ($m) => ['id' => $m->id, 'client' => $m->client->name, 'clientId' => $m->client_id,
                    'canView' => $m->can_view, 'canSubmitClaims' => $m->can_submit_claims, 'revoked' => (bool) $m->revoked_at]),
            ]),
            'invitations' => Invitation::with('client:id,name')->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->orderByDesc('created_at')->get()->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'email' => $i->email, 'role' => $i->role,
                    'client' => $i->client?->name, 'expiresAt' => $i->expires_at->toIso8601String()]),
            'clients' => Client::whereNull('archived_at')->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'inviteUrl' => session('invite_url'),
        ]);
    }

    public function invite(Request $request, InvitationService $svc)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150', 'email' => 'required|email|max:190',
            'role' => 'required|in:lawyer,client',
            'client_id' => 'nullable|required_if:role,client|exists:clients,id',
            'can_view' => 'boolean', 'can_submit_claims' => 'boolean',
        ]);
        $existing = User::where('email', mb_strtolower($data['email']))->first();
        if ($existing && $existing->role !== $data['role']) {
            return back()->withErrors(['email' => 'هذا البريد مستخدم لحساب بدور مختلف.']);
        }
        if ($data['role'] === 'client' && ! $request->boolean('can_view') && ! $request->boolean('can_submit_claims')) {
            return back()->withErrors(['can_view' => 'امنح صلاحية واحدة على الأقل.']);
        }
        $res = $svc->create($data, $request->user()->id);

        return back()->with('success', 'أُنشئت الدعوة وأُرسلت بالبريد. يمكنك نسخ الرابط أدناه (يظهر مرة واحدة).')->with('invite_url', $res['url']);
    }

    public function revokeInvitation(Invitation $invitation)
    {
        $invitation->forceFill(['revoked_at' => now()])->save();
        Audit::log('invitation.revoked', $invitation);

        return back()->with('success', 'أُلغيت الدعوة.');
    }

    public function deactivate(Request $request, User $user, AccountManager $accounts)
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك تعطيل حسابك.');
        $accounts->deactivate($user);

        return back()->with('success', 'عُطّل الحساب وأُنهيت جميع جلساته.');
    }

    public function reactivate(User $user, AccountManager $accounts)
    {
        $accounts->reactivate($user);

        return back()->with('success', 'أُعيد تفعيل الحساب.');
    }

    /** إعادة تعيين كلمة المرور برابط أحادي الاستخدام (يُنهي الجلسات الحالية) */
    public function resetPassword(User $user, InvitationService $svc, AccountManager $accounts)
    {
        abort_unless($user->is_active, 422, 'الحساب معطّل.');
        $res = $svc->create(['email' => $user->email, 'name' => $user->name, 'role' => $user->role, 'purpose' => 'reset'], request()->user()->id);
        $accounts->revokeSessions($user);

        return back()->with('success', 'أُنشئ رابط إعادة التعيين وأُرسل بالبريد.')->with('invite_url', $res['url']);
    }

    public function revokeSessions(User $user, AccountManager $accounts)
    {
        $accounts->revokeSessions($user);

        return back()->with('success', 'أُنهيت جميع جلسات المستخدم.');
    }

    public function updateMembership(Request $request, ClientMembership $membership, AccountManager $accounts)
    {
        $data = $request->validate(['can_view' => 'required|boolean', 'can_submit_claims' => 'required|boolean', 'revoked' => 'required|boolean']);
        $before = $membership->only(['can_view', 'can_submit_claims', 'revoked_at']);
        $membership->forceFill([
            'can_view' => $data['can_view'], 'can_submit_claims' => $data['can_submit_claims'],
            'revoked_at' => $data['revoked'] ? ($membership->revoked_at ?? now()) : null,
        ])->save();
        Audit::log('membership.updated', $membership, ['view' => $data['can_view'], 'claims' => $data['can_submit_claims'], 'revoked' => $data['revoked'],
            'user' => $membership->user_id, 'client' => $membership->client_id]);
        // تقليص الصلاحية يُبطل الجلسات القائمة حتى تُطبَّق فورًا على أي صفحة مفتوحة
        if ($data['revoked'] || ($before['can_view'] && ! $data['can_view']) || ($before['can_submit_claims'] && ! $data['can_submit_claims'])) {
            $accounts->revokeSessions($membership->user);
        }

        return back()->with('success', 'حُدّثت الصلاحيات.');
    }
}
