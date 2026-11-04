<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();
        $ok = match ($role) {
            'lawyer' => $user?->isLawyer(),
            'client' => $user?->isClientUser(),
            default => false,
        };
        abort_unless($ok, 403);

        return $next($request);
    }
}
