<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $g = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->g->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->g->getQRCodeUrl(config('office.short_name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /** يمنع إعادة استخدام الرمز نفسه خلال نافذته */
    public function verify(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $key = 'totp-used:'.$user->id.':'.$code;
        if (Cache::has($key)) {
            return false;
        }
        $ok = (bool) $this->g->verifyKey($secret, $code, 1);
        if ($ok) {
            Cache::put($key, true, 120);
        }

        return $ok;
    }

    /** @return array{plain: array<string>, hashed: array<string>} */
    public function recoveryCodes(): array
    {
        $plain = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();

        return ['plain' => $plain, 'hashed' => array_map(fn ($c) => Hash::make($c), $plain)];
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check(strtoupper(trim($code)), $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
