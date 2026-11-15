<?php

namespace App\Services\Media;

use App\Models\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * شعار العميل. يُعاد ترميز الصورة بـ GD إلى PNG (يُسقط أي محتوى مخفي أو بيانات وصفية)،
 * ويُصغَّر إلى 600×300 كحد أقصى، ويُحفظ على القرص الخاص لخادم المكتب — بلا أي خدمة خارجية.
 */
class ClientLogo
{
    private const DIR = 'client-logos';

    public static function store(Client $client, UploadedFile $file): void
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw ValidationException::withMessages(['logo' => 'تعذّرت قراءة الصورة. استخدم PNG أو JPG أو WEBP.']);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 600 / $w, 300 / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagepng($dst, null, 8);
        $png = (string) ob_get_clean();

        $path = self::DIR.'/'.$client->id.'-'.Str::lower(Str::random(12)).'.png';
        Storage::disk('local')->put($path, $png);
        $old = $client->logo_path;
        $client->forceFill(['logo_path' => $path])->save();
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }

    public static function remove(Client $client): void
    {
        if ($client->logo_path) {
            Storage::disk('local')->delete($client->logo_path);
            $client->forceFill(['logo_path' => null])->save();
        }
    }

    public static function exists(Client $client): bool
    {
        return $client->logo_path && Storage::disk('local')->exists($client->logo_path);
    }

    public static function dataUri(Client $client): ?string
    {
        return self::exists($client) ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($client->logo_path)) : null;
    }

    public static function response(Client $client)
    {
        abort_unless(self::exists($client), 404);

        return response(Storage::disk('local')->get($client->logo_path), 200, [
            'Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** رابط العرض مع بصمة تتغير عند تبديل الشعار */
    public static function url(Client $client, bool $forClient): ?string
    {
        if (! $client->logo_path) {
            return null;
        }
        $v = substr(md5($client->logo_path), 0, 8);

        return ($forClient ? "/portal/client-logo/{$client->id}" : "/office/clients/{$client->id}/logo")."?v={$v}";
    }
}
