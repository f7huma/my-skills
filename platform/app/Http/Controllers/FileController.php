<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Audit;
use App\Services\Files\FileVault;
use Illuminate\Http\Request;

/** كل عرض أو تنزيل يمر بتفويض الخادم لكل ملف ولكل طلب */
class FileController extends Controller
{
    public function show(Request $request, string $uuid, FileVault $vault)
    {
        $doc = Document::where('uuid', $uuid)->first();
        // نعيد 404 في جميع حالات الرفض كي لا يُستدل على وجود الملف
        if (! $doc || ! $request->user()->can('view', $doc)) {
            Audit::log('file.access_denied', $doc, ['uuid_prefix' => substr($uuid, 0, 8)]);
            abort(404);
        }
        Audit::log('file.downloaded', $doc, ['inline' => $request->boolean('inline')]);

        return $vault->download($doc, $request->boolean('inline'));
    }
}
