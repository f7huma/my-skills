<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request)
    {
        // «email» هنا حقل الدخول: بريد أو اسم مستخدم (يقبل المسافات)
        $data = $request->validate(['email' => 'required|string|max:190', 'password' => 'required|string|max:200', 'remember' => 'boolean'],
            ['email.required' => 'اكتب اسم المستخدم أو البريد الإلكتروني.']);
        $user = User::findForLogin($data['email']);
        // نتحقق من كلمة المرور حتى عند عدم وجود المستخدم لتقليل فروق التوقيت
        $valid = $user && $user->password && Hash::check($data['password'], $user->password);
        if (! $user) {
            Hash::check($data['password'], self::dummyHash());
        }
        if (! $valid || ! $user->is_active) {
            Audit::log('auth.failed', $user, ['reason' => $user && ! $user->is_active ? 'inactive' : 'credentials'], $user?->id);
            throw ValidationException::withMessages(['email' => 'بيانات الدخول غير صحيحة أو الحساب غير مفعّل.']);
        }

        if ($user->hasTwoFactor()) {
            $request->session()->put('login.id', $user->id);
            $request->session()->put('login.remember', (bool) ($data['remember'] ?? false));

            return redirect()->route('two-factor.challenge');
        }

        return $this->complete($request, $user, (bool) ($data['remember'] ?? false));
    }

    /** بصمة عشوائية تُولَّد مرة لكل عملية لموازنة زمن الاستجابة عند عدم وجود المستخدم */
    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(\Illuminate\Support\Str::random(40));
    }

    public function complete(Request $request, User $user, bool $remember)
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('auth_generation', $user->session_generation);
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $user, ['mfa' => $user->hasTwoFactor()]);

        return redirect()->intended(route('home'));
    }

    public function challenge(Request $request)
    {
        abort_unless($request->session()->has('login.id'), 403);

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verifyChallenge(Request $request, TwoFactor $tf)
    {
        $data = $request->validate(['code' => 'nullable|string|max:10', 'recovery_code' => 'nullable|string|max:20']);
        $user = User::find($request->session()->get('login.id'));
        abort_unless($user && $user->is_active && $user->hasTwoFactor(), 403);
        $ok = ! empty($data['code'])
            ? $tf->verify($user, $user->two_factor_secret, $data['code'])
            : (! empty($data['recovery_code']) && $tf->useRecoveryCode($user, $data['recovery_code']));
        if (! $ok) {
            Audit::log('auth.mfa_failed', $user, [], $user->id);
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح.']);
        }
        if (! empty($data['recovery_code'])) {
            Audit::log('auth.recovery_code_used', $user, [], $user->id);
        }
        $remember = (bool) $request->session()->pull('login.remember');
        $request->session()->forget('login.id');

        return $this->complete($request, $user, $remember);
    }

    public function destroy(Request $request)
    {
        Audit::log('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
