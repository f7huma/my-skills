<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;

class AccountManager
{
    /** إبطال جميع جلسات المستخدم فورًا */
    public function revokeSessions(User $user, ?string $exceptSessionId = null): void
    {
        $user->forceFill(['session_generation' => $user->session_generation + 1, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();
        Audit::log('auth.sessions_revoked', $user);
    }

    public function deactivate(User $user): void
    {
        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();
        $this->revokeSessions($user);
        Audit::log('user.deactivated', $user);
    }

    public function reactivate(User $user): void
    {
        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        Audit::log('user.reactivated', $user);
    }
}
