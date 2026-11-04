<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** تقييد وصول مستقل لبيئة المعاينة قبل شاشة الدخول (إضافة لتقييد الوكيل العكسي) */
class PreviewGate
{
    public function handle(Request $request, Closure $next)
    {
        $cfg = config('office.preview_gate');
        if (! $cfg['enabled'] || $request->is('up')) {
            return $next($request);
        }
        $user = $request->getUser();
        $pass = $request->getPassword();
        if ($user !== null && $cfg['user'] && $cfg['password_hash'] && hash_equals((string) $cfg['user'], $user) && password_verify((string) $pass, $cfg['password_hash'])) {
            return $next($request);
        }

        return response('Preview access restricted', 401, [
            'WWW-Authenticate' => 'Basic realm="preview", charset="UTF-8"',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
