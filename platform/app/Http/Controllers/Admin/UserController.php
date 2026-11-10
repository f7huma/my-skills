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
            'users' => User::with(['memberships.client:id,name'])->whereNull('closed_at')->orderBy('role')->orderBy('name')->get()->map(fn (User $u) => [
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

    /**
     * إنشاء حساب مباشرة باسم مستخدم وكلمة مرور يحددهما المكتب، دون دعوة بالبريد.
     * المكتب يرسل البيانات للعميل بنفسه. كلمة المرور لا تُخزَّن إلا مشفّرة ولا تُعاد في أي استجابة.
     */
    public function createAccount(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'username' => array_merge(['required'], array_slice(User::usernameRules(), 1)),
            'password' => ['required', \Illuminate\Validation\Rules\Password::min(4)],
            'email' => 'nullable|email|max:190|unique:users,email',
            'role' => 'required|in:lawyer,client',
            'client_id' => 'nullable|required_if:role,client|exists:clients,id',
            'can_view' => 'boolean', 'can_submit_claims' => 'boolean',
        ], ['username.required' => 'اكتب اسم المستخدم الذي سيدخل به.']);
        if ($data['role'] === 'client' && ! $request->boolean('can_view') && ! $request->boolean('can_submit_claims')) {
            return back()->withErrors(['can_view' => 'امنح صلاحية واحدة على الأقل.']);
        }
        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $user = User::create(['name' => $data['name'], 'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
                'username' => User::normalizeUsername($data['username']), 'password' => $data['password'], 'role' => $data['role'], 'is_active' => true]);
            if ($data['role'] === 'client') {
                ClientMembership::create(['client_id' => $data['client_id'], 'user_id' => $user->id, 'can_view' => $request->boolean('can_view'),
                    'can_submit_claims' => $request->boolean('can_submit_claims'), 'granted_by' => $request->user()->id]);
            }

            return $user;
        });
        Audit::log('account.created_directly', $user, ['role' => $user->role, 'client' => $data['client_id'] ?? null]);

        return back()->with('success', 'أُنشئ الحساب. أرسل للمستخدم اسم المستخدم وكلمة المرور ورابط المنصة.');
    }

    /** تعيين كلمة مرور جديدة مباشرة لمستخدم (يُنهي جلساته الحالية) */
    public function setPassword(Request $request, User $user, AccountManager $accounts)
    {
        $data = $request->validate(['password' => ['required', \Illuminate\Validation\Rules\Password::min(4)]]);
        $user->forceFill(['password' => $data['password']])->save();
        if ($user->id !== $request->user()->id) {
            $accounts->revokeSessions($user);
        }
        Audit::log('account.password_set_by_office', $user);

        return back()->with('success', 'عُيّنت كلمة المرور الجديدة. أرسلها للمستخدم.');
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

    /**
     * حذف عضوية العميل نهائيًا. إن لم يبقَ للمستخدم أي عضوية أخرى يُغلق حسابه نهائيًا:
     * يُعطّل، وتُنهى جلساته، ويُفرَّغ بريده واسم المستخدم ليمكن استخدامهما لحساب جديد.
     * لا يُحذف صف المستخدم نفسه لأن سجل التدقيق (للإضافة فقط) يشير إليه.
     */
    public function destroyMembership(ClientMembership $membership, AccountManager $accounts)
    {
        $user = $membership->user;
        $clientId = $membership->client_id;
        \Illuminate\Support\Facades\DB::transaction(function () use ($membership, $user, $accounts) {
            $membership->delete();
            if ($user && $user->role === 'client' && ! $user->memberships()->exists()) {
                $user->forceFill(['is_active' => false, 'closed_at' => now(), 'email' => null, 'username' => null,
                    'password' => \Illuminate\Support\Str::random(64)])->save();
                $accounts->revokeSessions($user);
            } elseif ($user) {
                $accounts->revokeSessions($user);
            }
        });
        Audit::log('membership.deleted', null, ['user' => $user?->id, 'client' => $clientId, 'account_closed' => (bool) $user?->fresh()->closed_at]);

        return back()->with('success', $user?->fresh()->closed_at ? 'حُذفت العضوية وأُغلق الحساب نهائيًا.' : 'حُذفت العضوية نهائيًا.');
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
