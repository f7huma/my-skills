<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\Auth\AccountManager;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SecurityController extends Controller
{
    public function show(Request $request, TwoFactor $tf)
    {
        $user = $request->user();
        $pending = $request->session()->get('2fa.pending_secret');

        return Inertia::render('Account/Security', [
            'twoFactorEnabled' => $user->hasTwoFactor(),
            'mfaRequired' => $user->role === 'lawyer' && config('office.mfa_required'),
            'setup' => $pending ? ['qr' => $tf->qrSvg($user, $pending), 'secret' => $pending] : null,
            'recoveryCodes' => $request->session()->get('2fa.recovery_codes'),
            'remainingRecoveryCodes' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function startTwoFactor(Request $request, TwoFactor $tf)
    {
        $this->confirmPassword($request);
        $request->session()->put('2fa.pending_secret', $tf->newSecret());

        return back();
    }

    public function confirmTwoFactor(Request $request, TwoFactor $tf)
    {
        $request->validate(['code' => 'required|string|max:10']);
        $secret = $request->session()->get('2fa.pending_secret');
        $user = $request->user();
        if (! $secret || ! $tf->verify($user, $secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح. تأكد من توقيت جهازك وأعد المحاولة.']);
        }
        $codes = $tf->recoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes['hashed'], 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa.pending_secret');
        $request->session()->flash('2fa.recovery_codes', $codes['plain']);
        Audit::log('auth.mfa_enabled', $user);

        return back()->with('success', 'فُعّلت المصادقة الثنائية. احفظ رموز الاسترداد في مكان آمن.');
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactor $tf)
    {
        $this->confirmPassword($request);
        $codes = $tf->recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes['hashed']])->save();
        $request->session()->flash('2fa.recovery_codes', $codes['plain']);
        Audit::log('auth.recovery_codes_regenerated', $request->user());

        return back();
    }

    public function disableTwoFactor(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        if ($user->role === 'lawyer' && config('office.mfa_required')) {
            throw ValidationException::withMessages(['password' => 'المصادقة الثنائية إلزامية لحساب المحامي في هذه البيئة.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('auth.mfa_disabled', $user);

        return back()->with('success', 'أُلغيت المصادقة الثنائية.');
    }

    public function updatePassword(Request $request, AccountManager $accounts)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        }
        $user->forceFill(['password' => $data['password']])->save();
        $accounts->revokeSessions($user, $request->session()->getId());
        $request->session()->put('auth_generation', $user->fresh()->session_generation);
        Audit::log('auth.password_changed', $user);

        return back()->with('success', 'تم تغيير كلمة المرور وإنهاء الجلسات الأخرى.');
    }

    public function logoutOtherSessions(Request $request, AccountManager $accounts)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        $accounts->revokeSessions($user, $request->session()->getId());
        $request->session()->put('auth_generation', $user->fresh()->session_generation);

        return back()->with('success', 'أُنهيت جميع الجلسات الأخرى.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => 'required|string']);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'كلمة المرور غير صحيحة.']);
        }
    }
}
