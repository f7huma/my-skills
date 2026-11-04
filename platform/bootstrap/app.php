<?php

use App\Http\Middleware\EnsureActiveSession;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreviewGate;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '172.16.0.0/12,10.0.0.0/8,192.168.0.0/16,127.0.0.1'));
        $middleware->prepend(PreviewGate::class);
        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureActiveSession::class,
            RequireTwoFactor::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // لا تُكتب قيم الحقول الحساسة في السجلات
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'recovery_code', 'token']);

        // أخطاء قاعدة البيانات تُسجَّل دون القيم المرتبطة (قد تحوي أسماء عملاء أو هويات)
        $exceptions->report(function (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('db.query_failed', ['sqlstate' => $e->errorInfo[0] ?? $e->getCode(), 'sql' => $e->getSql()]);

            return false;
        });
        $exceptions->dontReportDuplicates();

        // رسائل أخطاء آمنة بالعربية دون كشف التفاصيل الداخلية
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();
            if (! app()->hasDebugModeEnabled() && in_array($status, [403, 404, 419, 429, 500, 503], true) && ! $request->expectsJson()) {
                if ($status === 419) {
                    return back()->with('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
                }

                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }
            if (in_array($status, [403, 404], true) && $request->header('X-Inertia') && ! app()->hasDebugModeEnabled()) {
                return Inertia::render('Error', ['status' => $status])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
