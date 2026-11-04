<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupRun;
use App\Models\SystemCheck;
use App\Services\Ops\HealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
        $latest = SystemCheck::query()->whereIn('id', SystemCheck::selectRaw('max(id)')->groupBy('check'))->get()->keyBy('check');

        return Inertia::render('Admin/System', [
            'checks' => collect(['backup' => 'النسخ الاحتياطي', 'disk' => 'مساحة القرص', 'queue' => 'الطوابير والعامل', 'scanner' => 'فحص الملفات', 'tls' => 'شهادة TLS'])
                ->map(fn ($label, $k) => ['key' => $k, 'label' => $label, 'status' => $latest->get($k)?->status ?? 'unknown', 'message' => $latest->get($k)?->message ?? 'لم يُشغّل الفحص بعد.',
                    'checkedAt' => $latest->get($k)?->checked_at?->toIso8601String()])->values(),
            'failures' => SystemCheck::where('status', 'fail')->latest('checked_at')->limit(20)->get()->map(fn ($c) => ['check' => $c->check, 'message' => $c->message, 'at' => $c->checked_at->toIso8601String()]),
            'backups' => BackupRun::latest('started_at')->limit(15)->get()->map(fn ($b) => ['name' => $b->name, 'status' => $b->status, 'size' => $b->size, 'files' => $b->file_count,
                'offsite' => $b->offsite_copied, 'startedAt' => $b->started_at->toIso8601String(), 'verifiedAt' => $b->verified_at?->toIso8601String(), 'error' => $b->error]),
            'environment' => app()->environment(),
            'appUrl' => config('app.url'),
            'scannerDriver' => config('attachments.scanner.driver'),
            'mfaRequired' => config('office.mfa_required'),
        ]);
    }

    public function runChecks(HealthService $health)
    {
        $health->runAll();

        return back()->with('success', 'اكتملت الفحوص.');
    }

    public function audit(Request $request)
    {
        $f = $request->validate(['action' => 'nullable|string|max:80', 'user' => 'nullable|integer']);
        $logs = AuditLog::with('user:id,name')
            ->when($f['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('Admin/Audit', [
            'logs' => $logs->through(fn ($l) => ['id' => $l->id, 'action' => $l->action, 'user' => $l->user?->name, 'subject' => $l->subject_type ? $l->subject_type.' #'.$l->subject_id : null,
                'meta' => $l->meta, 'ip' => $l->ip, 'at' => $l->created_at->toIso8601String()]),
            'filters' => $f,
        ]);
    }
}
