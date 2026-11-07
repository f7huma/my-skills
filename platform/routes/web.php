<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Office;
use App\Http\Controllers\Portal;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// ------------------------------------------------------------------ عام
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/two-factor', [LoginController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [LoginController::class, 'verifyChallenge'])->middleware('throttle:two-factor');
});
Route::get('/invitation/{token}', [InvitationController::class, 'show'])->middleware('throttle:invitations')->name('invitation.show');
Route::post('/invitation/{token}', [InvitationController::class, 'accept'])->middleware('throttle:invitations')->name('invitation.accept');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => auth()->user()->isLawyer() ? redirect()->route('dashboard') : redirect()->route('portal.home'))->name('home');

    Route::prefix('account/security')->name('security.')->controller(SecurityController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('/two-factor', 'startTwoFactor')->name('start');
        Route::post('/two-factor/confirm', 'confirmTwoFactor')->name('confirm');
        Route::post('/two-factor/recovery-codes', 'regenerateRecoveryCodes')->name('recovery');
        Route::delete('/two-factor', 'disableTwoFactor')->name('disable');
        Route::put('/password', 'updatePassword')->name('password');
        Route::put('/username', 'updateUsername')->name('username');
        Route::post('/sessions/logout-others', 'logoutOtherSessions')->name('logout-others');
    });

    // تنزيل الملفات: تفويض لكل ملف ولكل طلب (DocumentPolicy)
    Route::get('/files/{uuid}', [FileController::class, 'show'])->whereUuid('uuid')->middleware('throttle:downloads')->name('files.show');

    // ------------------------------------------------------------------ بوابة العميل
    Route::prefix('portal')->name('portal.')->middleware('role:client')->group(function () {
        Route::get('/', [Portal\PortalController::class, 'home'])->name('home');
        Route::get('/cases/{case}', [Portal\PortalController::class, 'showCase'])->whereNumber('case')->name('cases.show');
        Route::get('/executions/{execution}', [Portal\PortalController::class, 'showExecution'])->whereNumber('execution')->name('executions.show');
        Route::controller(Portal\ClaimController::class)->prefix('claims')->name('claims.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{claim}', 'show')->whereNumber('claim')->name('show');
            Route::get('/{claim}/edit', 'edit')->whereNumber('claim')->name('edit');
            Route::put('/{claim}', 'update')->whereNumber('claim')->name('update');
            Route::post('/{claim}/documents', 'upload')->whereNumber('claim')->middleware('throttle:uploads')->name('upload');
            Route::delete('/{claim}/documents/{uuid}', 'deleteDocument')->whereNumber('claim')->name('documents.destroy');
            Route::post('/{claim}/submit', 'submit')->whereNumber('claim')->name('submit');
        });
    });

    // ------------------------------------------------------------------ مساحة المحامي
    Route::prefix('office')->middleware('role:lawyer')->group(function () {
        Route::get('/', [Office\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/search', [Office\DashboardController::class, 'search'])->name('search');

        Route::controller(Office\ClientController::class)->group(function () {
            Route::get('/clients', 'index')->name('clients.index');
            Route::get('/clients/create', 'create')->name('clients.create');
            Route::post('/clients', 'store')->name('clients.store');
            Route::get('/clients/{client}', 'show')->name('clients.show');
            Route::get('/clients/{client}/edit', 'edit')->name('clients.edit');
            Route::put('/clients/{client}', 'update')->name('clients.update');
            Route::post('/clients/{client}/archive', 'archive')->name('clients.archive');
            Route::post('/clients/{client}/poas', 'storePoa')->name('poas.store');
            Route::put('/clients/{client}/poas/{poa}', 'updatePoa')->name('poas.update');
        });

        Route::post('/documents', [Office\DocumentController::class, 'store'])->middleware('throttle:uploads')->name('documents.store');
        Route::post('/documents/{document}/share', [Office\DocumentController::class, 'share'])->name('documents.share');
        Route::post('/documents/{document}/rescan', [Office\DocumentController::class, 'rescan'])->name('documents.rescan');
        Route::delete('/documents/{document}', [Office\DocumentController::class, 'destroy'])->name('documents.destroy');

        Route::controller(Office\CaseController::class)->group(function () {
            Route::get('/cases', 'index')->name('cases.index');
            Route::get('/cases/create', 'create')->name('cases.create');
            Route::post('/cases', 'store')->name('cases.store');
            Route::get('/cases/{case}', 'show')->name('cases.show');
            Route::put('/cases/{case}', 'update')->name('cases.update');
            Route::post('/cases/{case}/status', 'updateStatus')->name('cases.status');
            Route::post('/cases/{case}/client-summary', 'clientSummary')->name('cases.client-summary');
            Route::post('/cases/{case}/parties', 'storeParty')->name('cases.parties.store');
            Route::delete('/cases/{case}/parties/{party}', 'destroyParty')->name('cases.parties.destroy');
            Route::post('/filters', 'saveFilter')->name('filters.store');
            Route::delete('/filters/{filter}', 'deleteFilter')->name('filters.destroy');
        });

        Route::controller(Office\ProceedingsController::class)->group(function () {
            Route::post('/cases/{case}/reconciliations', 'storeReconciliation')->name('reconciliations.store');
            Route::put('/reconciliations/{reconciliation}', 'updateReconciliation')->name('reconciliations.update');
            Route::post('/reconciliations/{reconciliation}/outcome', 'reconciliationOutcome')->name('reconciliations.outcome');
            Route::post('/reconciliations/{reconciliation}/move-to-court', 'moveToCourt')->name('reconciliations.move');
            Route::post('/cases/{case}/filings', 'storeFiling')->name('filings.store');
            Route::put('/filings/{filing}', 'updateFiling')->name('filings.update');
            Route::post('/cases/{case}/hearings', 'storeHearing')->name('hearings.store');
            Route::get('/hearings/{hearing}/preview', 'previewChange')->name('hearings.preview');
            Route::post('/hearings/{hearing}/change', 'changeHearing')->name('hearings.change');
            Route::post('/hearings/{hearing}/complete', 'completeHearing')->name('hearings.complete');
            Route::post('/cases/{case}/tasks', 'storeTask')->name('cases.tasks.store');
            Route::put('/tasks/{task}', 'updateTask')->name('tasks.update');
            Route::post('/tasks/{task}/status', 'toggleTask')->name('tasks.status');
            Route::post('/cases/{case}/judgment', 'saveJudgment')->name('judgment.save');
            Route::post('/cases/{case}/judgment/finality', 'confirmFinality')->name('judgment.finality');
            Route::post('/cases/{case}/judgment/execution', 'executionDecision')->name('judgment.execution');
        });

        Route::get('/tasks', [Office\TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [Office\TaskController::class, 'store'])->name('tasks.store');

        Route::post('/cases/{case}/memos', [Office\MemoController::class, 'store'])->name('memos.store');
        Route::get('/memos/{memo}', [Office\MemoController::class, 'edit'])->name('memos.edit');
        Route::put('/memos/{memo}', [Office\MemoController::class, 'update'])->name('memos.update');
        Route::post('/memos/{memo}/status', [Office\MemoController::class, 'status'])->name('memos.status');
        Route::get('/memos/{memo}/versions/{version}', [Office\MemoController::class, 'version'])->name('memos.version');
        Route::get('/memos/{memo}/print', [Office\MemoController::class, 'print'])->name('memos.print');

        Route::get('/deadlines', [Office\DeadlineController::class, 'index'])->name('deadlines.index');
        Route::post('/deadlines', [Office\DeadlineController::class, 'store'])->name('deadlines.store');
        Route::put('/deadlines/{deadline}', [Office\DeadlineController::class, 'update'])->name('deadlines.update');
        Route::post('/deadlines/{deadline}/toggle', [Office\DeadlineController::class, 'close'])->name('deadlines.toggle');

        Route::post('/collections', [Office\CollectionController::class, 'store'])->name('collections.store');
        Route::delete('/collections/{collection}', [Office\CollectionController::class, 'destroy'])->name('collections.destroy');

        Route::controller(Office\ExecutionController::class)->group(function () {
            Route::get('/executions', 'index')->name('executions.index');
            Route::get('/executions/create', 'create')->name('executions.create');
            Route::post('/executions', 'store')->name('executions.store');
            Route::get('/executions/{execution}', 'show')->name('executions.show');
            Route::put('/executions/{execution}', 'update')->name('executions.update');
            Route::post('/executions/{execution}/parties', 'storeParty')->name('executions.parties.store');
            Route::post('/executions/{execution}/decisions', 'storeDecision')->name('executions.decisions.store');
            Route::post('/executions/{execution}/actions', 'storeAction')->name('executions.actions.store');
            Route::put('/execution-actions/{action}', 'updateAction')->name('executions.actions.update');
            Route::post('/executions/{execution}/followup', 'logFollowup')->name('executions.followup');
            Route::post('/executions/{execution}/tasks', 'storeTask')->name('executions.tasks.store');
        });

        Route::controller(Office\ClaimInboxController::class)->group(function () {
            Route::get('/claims', 'index')->name('claims.index');
            Route::get('/claims/{claim}', 'show')->name('claims.show');
            Route::post('/claims/{claim}/request-completion', 'requestCompletion')->name('claims.request-completion');
            Route::post('/claims/{claim}/note', 'note')->name('claims.note');
            Route::put('/claims/{claim}/internal', 'internal')->name('claims.internal');
            Route::post('/claims/{claim}/convert-case', 'convertCase')->name('claims.convert-case');
            Route::post('/claims/{claim}/convert-execution', 'convertExecution')->name('claims.convert-execution');
            Route::post('/claims/{claim}/close', 'close')->name('claims.close');
        });

        Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/preview', 'preview')->name('preview');
            Route::get('/pdf', 'pdf')->middleware('throttle:reports')->name('pdf');
        });

        Route::prefix('admin')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('admin.users');
            Route::post('/invitations', [UserController::class, 'invite'])->name('admin.invite');
            Route::delete('/invitations/{invitation}', [UserController::class, 'revokeInvitation'])->name('admin.invitations.revoke');
            Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('admin.users.deactivate');
            Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('admin.users.reactivate');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
            Route::post('/users/{user}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('admin.users.revoke-sessions');
            Route::put('/memberships/{membership}', [UserController::class, 'updateMembership'])->name('admin.memberships.update');

            Route::controller(SettingsController::class)->group(function () {
                Route::get('/settings', 'index')->name('admin.settings');
                Route::put('/settings/general', 'general')->name('admin.settings.general');
                Route::post('/categories', 'storeCategory')->name('admin.categories.store');
                Route::put('/categories/{category}', 'updateCategory')->name('admin.categories.update');
                Route::post('/action-types', 'saveActionType')->name('admin.action-types.store');
                Route::put('/action-types/{actionType}', 'saveActionType')->name('admin.action-types.update');
                Route::post('/holidays', 'storeHoliday')->name('admin.holidays.store');
                Route::put('/holidays/{holiday}', 'updateHoliday')->name('admin.holidays.update');
                Route::delete('/holidays/{holiday}', 'destroyHoliday')->name('admin.holidays.destroy');
                Route::post('/rules', 'storeRule')->name('admin.rules.store');
                Route::post('/rules/{rule}/status', 'ruleStatus')->name('admin.rules.status');
            });

            Route::get('/system', [SystemController::class, 'index'])->name('admin.system');
            Route::post('/system/run-checks', [SystemController::class, 'runChecks'])->name('admin.system.checks');
            Route::get('/audit', [SystemController::class, 'audit'])->name('admin.audit');
        });
    });
});
