<?php

namespace App\Http\Middleware;

use App\Models\Claim;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $shared = [
            'office' => [
                'name' => config('office.name'),
                'shortName' => config('office.short_name'),
                'tagline' => config('office.tagline'),
                'license' => config('office.license_number'),
            ],
            'env' => app()->environment('production') ? null : app()->environment(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'inviteUrl' => fn () => $request->session()->get('invite_url'),
            ],
            'auth' => null,
        ];
        if ($user) {
            $shared['auth'] = [
                'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'twoFactor' => $user->hasTwoFactor()],
            ];
            if ($user->isLawyer()) {
                $shared['nav'] = fn () => [
                    'claimsInbox' => Claim::query()->whereIn('status', ['under_review'])->count(),
                ];
            }
            if ($user->isClientUser()) {
                $viewIds = $user->clientIdsFor('view');
                $claimIds = $user->clientIdsFor('claims');
                $shared['portal'] = [
                    'clients' => \App\Models\Client::query()->whereIn('id', array_unique(array_merge($viewIds, $claimIds)))->get(['id', 'name'])
                        ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'canView' => in_array($c->id, $viewIds, true), 'canSubmitClaims' => in_array($c->id, $claimIds, true)]),
                ];
            }
        }

        return array_merge(parent::share($request), $shared);
    }
}
