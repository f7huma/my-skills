<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();
        $response = $next($request);

        $dev = app()->environment('local') && is_file(public_path('hot'));
        $devSrc = $dev ? ' http://localhost:5173 ws://localhost:5173 http://127.0.0.1:5173 ws://127.0.0.1:5173 http://[::1]:5173 ws://[::1]:5173' : '';
        $h = $response->headers;
        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'".$devSrc,
                "style-src 'self' 'unsafe-inline'".$devSrc,
                "img-src 'self' data: blob:",
                "font-src 'self' data:".$devSrc,
                "connect-src 'self'".$devSrc,
                "frame-src 'self'",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }
        $h->set('X-Content-Type-Options', 'nosniff');
        if (! $h->has('X-Frame-Options')) {
            $h->set('X-Frame-Options', 'DENY');
        }
        $h->set('Referrer-Policy', 'same-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if (config('office.noindex')) {
            $h->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        if ($request->user()) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
