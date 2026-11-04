<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\Document;
use App\Models\User;

/**
 * منع افتراضي: لا يُعرض ملف إلا إذا تحقق شرط صريح.
 * - المحامي: أي ملف سليم الفحص وغير محذوف.
 * - مستخدم العميل: ملف سليم يخص عميلًا له عضوية سارية عليه، وإما شاركه المحامي صراحة،
 *   أو رفعه العميل نفسه ضمن مطالبة تخص العميل نفسه.
 */
class DocumentPolicy
{
    public function view(User $user, Document $doc): bool
    {
        if (! $doc->isAvailable()) {
            return false;
        }
        if ($user->isLawyer()) {
            return true;
        }
        if (! $user->isClientUser() || ! $doc->client_id) {
            return false;
        }
        if ($doc->shared_with_client && $user->canAccessClient($doc->client_id, 'view')) {
            return true;
        }
        if ($doc->documentable_type === (new Claim)->getMorphClass()) {
            $claim = Claim::find($doc->documentable_id);

            return $claim && $claim->client_id === $doc->client_id && $user->canAccessClient($claim->client_id, 'claims');
        }

        return false;
    }

    public function delete(User $user, Document $doc): bool
    {
        if ($user->isLawyer()) {
            return $doc->deleted_at === null;
        }
        if ($user->isClientUser() && $doc->documentable_type === (new Claim)->getMorphClass() && $doc->uploaded_by === $user->id) {
            $claim = Claim::find($doc->documentable_id);

            return $claim && $claim->isEditableByClient() && $user->canAccessClient($claim->client_id, 'claims');
        }

        return false;
    }
}
