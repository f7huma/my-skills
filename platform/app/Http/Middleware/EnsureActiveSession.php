<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * يتحقق في كل طلب من أن الحساب ما زال مفعّلًا وأن الجلسة لم تُبطل (session_generation).
 * تعطيل المستخدم أو إبطال جلساته يمنع الوصول فورًا حتى لو بقي ملف تعريف الارتباط لديه.
 */
class EnsureActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user) {
            $gen = $request->session()->get('auth_generation');
            // قراءة حديثة من قاعدة البيانات في كل طلب (لا نعتمد على نسخة مخزنة من المستخدم)
            $fresh = \App\Models\User::query()->whereKey($user->getKey())->first(['id', 'is_active', 'session_generation']);
            if ($fresh) {
                $user->forceFill(['is_active' => $fresh->is_active, 'session_generation' => $fresh->session_generation])->syncOriginal();
            }
            if (! $fresh || ! $user->is_active || ($gen !== null && (int) $gen !== (int) $user->session_generation)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() && ! $request->header('X-Inertia')
                    ? response()->json(['message' => 'انتهت الجلسة.'], 401)
                    : redirect()->route('login')->with('status', 'انتهت الجلسة. سجّل الدخول مرة أخرى.');
            }
        }

        return $next($request);
    }
}
