<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $inv = Invitation::where('token_hash', Invitation::hashToken($token))->first();
        if (! $inv || ! $inv->isUsable()) {
            return Inertia::render('Auth/InvitationInvalid');
        }
        $existing = User::where('email', $inv->email)->exists();

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'invitation' => ['name' => $inv->name, 'email' => $inv->email, 'existingAccount' => $existing && $inv->purpose !== 'reset', 'reset' => $inv->purpose === 'reset'],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(4)],
        ]);

        $user = DB::transaction(function () use ($token, $data) {
            // قفل الصف يضمن أن الرابط أحادي الاستخدام حتى مع الضغط المزدوج
            $inv = Invitation::where('token_hash', Invitation::hashToken($token))->lockForUpdate()->first();
            if (! $inv || ! $inv->isUsable()) {
                throw ValidationException::withMessages(['password' => 'رابط الدعوة غير صالح أو انتهت صلاحيته.']);
            }
            $user = User::where('email', $inv->email)->first();
            if ($user) {
                if (! $user->is_active || $user->role !== $inv->role) {
                    throw ValidationException::withMessages(['password' => 'لا يمكن قبول الدعوة لهذا الحساب. تواصل مع المكتب.']);
                }
                if ($inv->purpose === 'reset') {
                    $user->forceFill(['password' => $data['password']])->save();
                    app(\App\Services\Auth\AccountManager::class)->revokeSessions($user);
                    $user->refresh();
                } elseif (! \Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages(['password' => 'لديك حساب قائم: أدخل كلمة المرور الحالية لربط الدعوة به.']);
                }
            } else {
                $user = User::create(['name' => $inv->name, 'email' => $inv->email, 'password' => $data['password'], 'role' => $inv->role, 'is_active' => true]);
            }
            if ($inv->client_id) {
                ClientMembership::updateOrCreate(
                    ['client_id' => $inv->client_id, 'user_id' => $user->id],
                    ['can_view' => $inv->can_view, 'can_submit_claims' => $inv->can_submit_claims, 'revoked_at' => null, 'granted_by' => $inv->invited_by],
                );
            }
            $inv->forceFill(['accepted_at' => now(), 'user_id' => $user->id])->save();
            Audit::log('invitation.accepted', $inv, ['role' => $inv->role], $user->id);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth_generation', $user->session_generation);
        Audit::log('auth.login', $user, ['via' => 'invitation']);

        return redirect()->route('home');
    }
}
