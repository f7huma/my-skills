<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/** فحص أولي: الحجم والامتداد وتوقيع الملف ومحتواه قبل دخوله الحجر */
class FileInspector
{
    /** @return array{ok:bool, reason?:string, mime?:string, ext?:string} */
    public function inspect(UploadedFile $file): array
    {
        $max = config('attachments.max_kb') * 1024;
        if ($file->getSize() <= 0) {
            return ['ok' => false, 'reason' => 'الملف فارغ.'];
        }
        if ($file->getSize() > $max) {
            return ['ok' => false, 'reason' => 'حجم الملف يتجاوز الحد المسموح.'];
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $allowed = config('attachments.allowed');
        if (! isset($allowed[$ext])) {
            return ['ok' => false, 'reason' => 'نوع الملف غير مسموح. المسموح: PDF وصور JPG/PNG وملفات Word/Excel الحديثة.'];
        }
        $path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        if (! in_array($mime, $allowed[$ext], true)) {
            return ['ok' => false, 'reason' => 'محتوى الملف لا يطابق امتداده.'];
        }
        $h = fopen($path, 'rb');
        $head = fread($h, 8);
        fclose($h);
        $sig = match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'png' => $head === "\x89PNG\r\n\x1a\n",
            'jpg', 'jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'docx', 'xlsx' => str_starts_with($head, "PK\x03\x04"),
            default => false,
        };
        if (! $sig) {
            return ['ok' => false, 'reason' => 'توقيع الملف غير صالح.'];
        }
        if (in_array($ext, ['docx', 'xlsx'], true)) {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || $zip->locateName('[Content_Types].xml') === false) {
                return ['ok' => false, 'reason' => 'ملف Office تالف أو غير صالح.'];
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = strtolower((string) $zip->getNameIndex($i));
                if (str_contains($n, 'vbaproject') || str_ends_with($n, '.bin') && str_contains($n, 'activex')) {
                    $zip->close();

                    return ['ok' => false, 'reason' => 'الملفات التي تحتوي على وحدات ماكرو غير مسموحة.'];
                }
            }
            $zip->close();
            $mime = $ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }
        if ($ext === 'pdf') {
            $content = file_get_contents($path);
            if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia)\b#', $content)) {
                return ['ok' => false, 'reason' => 'ملف PDF يحتوي على عناصر نشطة (سكربت أو ملفات مضمّنة) وهي غير مسموحة.'];
            }
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg'], true) && @getimagesize($path) === false) {
            return ['ok' => false, 'reason' => 'الصورة تالفة.'];
        }

        return ['ok' => true, 'mime' => $mime, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
    }
}
