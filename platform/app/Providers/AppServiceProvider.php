<?php

namespace App\Providers;

use App\Services\Calendar;
use App\Services\Files\ClamAvScanner;
use App\Services\Files\DisabledScanner;
use App\Services\Files\FakeScanner;
use App\Services\Files\FileCipher;
use App\Services\Files\MalwareScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Calendar::class);
        $this->app->singleton(FileCipher::class, fn () => new FileCipher(config('attachments.key'), config('attachments.previous_keys')));
        $this->app->bind(MalwareScanner::class, function () {
            $s = config('attachments.scanner');

            return match ($s['driver']) {
                'clamav' => new ClamAvScanner($s['host'], $s['port'], $s['timeout']),
                'fake' => app()->environment(['local', 'testing']) ? new FakeScanner
                    : throw new RuntimeException('Fake scanner is not allowed outside local/testing'),
                default => new DisabledScanner,
            };
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $r->input('email')))).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id').'|'.$r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('downloads', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('reports', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('invitations', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    }
}
