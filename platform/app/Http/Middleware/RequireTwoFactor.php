<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** في الإنتاج: يجب أن يكون للمحامي مصادقة ثنائية مفعّلة قبل الوصول إلى البيانات */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->role === 'lawyer' && config('office.mfa_required') && ! $user->hasTwoFactor()) {
            if (! $request->routeIs('security.*', 'logout')) {
                return redirect()->route('security.show')->with('status', 'فعّل المصادقة الثنائية للمتابعة.');
            }
        }

        return $next($request);
    }
}
