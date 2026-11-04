<?php

namespace App\Services\Files;

use App\Jobs\ScanDocument;
use App\Models\Document;
use App\Services\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * المخزن الخاص للمرفقات: خارج جذر الويب، مشفّر، ولا يُتاح الملف قبل نجاح الفحص.
 */
class FileVault
{
    public function __construct(private FileCipher $cipher, private FileInspector $inspector, private MalwareScanner $scanner) {}

    public function root(): string
    {
        return rtrim(config('attachments.root'), '/');
    }

    /**
     * @param  array{client_id:int|null, category:string, party?:string, label?:string|null, shared_with_client?:bool}  $attrs
     */
    public function store(UploadedFile $file, array $attrs, ?Model $owner = null, string $field = 'file'): Document
    {
        $check = $this->inspector->inspect($file);
        if (! $check['ok']) {
            Audit::log('file.rejected', $owner, ['reason' => $check['reason']]);
            throw ValidationException::withMessages([$field => $check['reason']]);
        }

        $uuid = (string) Str::uuid();
        $rel = 'objects/'.substr($uuid, 0, 2).'/'.substr($uuid, 2, 2).'/'.$uuid.'.enc';
        $abs = $this->root().'/'.$rel;
        @mkdir(dirname($abs), 0700, true);
        $in = fopen($file->getRealPath(), 'rb');
        $tmp = $abs.'.part';
        $out = fopen($tmp, 'wb');
        $keyId = $this->cipher->encryptStream($in, $out);
        fclose($in);
        fclose($out);
        rename($tmp, $abs);
        chmod($abs, 0600);

        $doc = new Document([
            'uuid' => $uuid,
            'client_id' => $attrs['client_id'],
            'category' => $attrs['category'],
            'party' => $attrs['party'] ?? 'client',
            'label' => $attrs['label'] ?? null,
            'original_name' => $this->safeName($file->getClientOriginalName(), $check['ext']),
            'extension' => $check['ext'],
            'mime' => $check['mime'],
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'storage_path' => $rel,
            'key_id' => $keyId,
            'scan_status' => 'pending',
            'shared_with_client' => (bool) ($attrs['shared_with_client'] ?? false),
            'uploaded_by' => auth()->id(),
        ]);
        if ($doc->shared_with_client) {
            $doc->shared_at = now();
            $doc->shared_by = auth()->id();
        }
        if ($owner) {
            $doc->documentable()->associate($owner);
        }
        $doc->save();
        Audit::log('file.uploaded', $doc, ['category' => $doc->category, 'size' => $doc->size]);
        ScanDocument::dispatch($doc->id)->afterCommit();

        return $doc;
    }

    private function safeName(string $name, string $ext): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N}\s\-_.()]+/u', '', $base) ?: 'document';

        return mb_substr(trim($base), 0, 120).'.'.$ext;
    }

    /** @param callable(string):void $sink */
    public function read(Document $doc, callable $sink): void
    {
        $abs = $this->root().'/'.$doc->storage_path;
        if (! is_file($abs)) {
            throw new RuntimeException('Stored file missing');
        }
        $h = fopen($abs, 'rb');
        try {
            $this->cipher->decryptStream($h, $sink);
        } finally {
            fclose($h);
        }
    }

    public function scan(Document $doc): string
    {
        $result = $this->scanner->scan(fn (callable $sink) => $this->read($doc, $sink));
        $doc->scan_attempts++;
        $doc->scan_engine = $result['engine'];
        $doc->scan_status = $result['status'];
        $doc->scanned_at = now();
        $doc->save();
        Audit::log('file.scanned', $doc, ['status' => $result['status'], 'engine' => $result['engine']], null);
        if ($result['status'] === 'infected') {
            // يُحذف المحتوى المشفّر فورًا ويبقى السجل للتتبع
            @unlink($this->root().'/'.$doc->storage_path);
        }

        return $result['status'];
    }

    public function download(Document $doc, bool $inline = false): StreamedResponse
    {
        $name = $doc->original_name;
        $disposition = ($inline && in_array($doc->extension, ['pdf', 'png', 'jpg'], true)) ? 'inline' : 'attachment';

        return new StreamedResponse(function () use ($doc) {
            $this->read($doc, function (string $c) {
                echo $c;
                flush();
            });
        }, 200, [
            'Content-Type' => $doc->mime,
            'Content-Length' => (string) $doc->size,
            'Content-Disposition' => $disposition."; filename=\"document.{$doc->extension}\"; filename*=UTF-8''".rawurlencode($name),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            // عزل معاينة المستندات غير الموثوقة: لا سكربت ولا موارد خارجية ولا تضمين
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; plugin-types application/pdf; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    public function softDelete(Document $doc, string $reason): void
    {
        $doc->forceFill(['deleted_at' => now(), 'deleted_by' => auth()->id(), 'deletion_reason' => mb_substr($reason, 0, 250), 'shared_with_client' => false])->save();
        Audit::log('file.deleted', $doc, ['reason' => $reason]);
    }

    /** تدوير المفتاح: يعيد تشفير الملفات المشفرة بمفاتيح سابقة بالمفتاح الحالي */
    public function reencrypt(Document $doc): bool
    {
        if ($doc->key_id === $this->cipher->currentKeyId()) {
            return false;
        }
        $abs = $this->root().'/'.$doc->storage_path;
        $plainTmp = fopen('php://temp/maxmemory:8388608', 'w+b');
        $this->read($doc, fn ($c) => fwrite($plainTmp, $c));
        rewind($plainTmp);
        if (hash_final(tap(hash_init('sha256'), function ($ctx) use ($plainTmp) {
            hash_update_stream($ctx, $plainTmp);
        })) !== $doc->sha256) {
            throw new RuntimeException('Checksum mismatch during re-encryption');
        }
        rewind($plainTmp);
        $out = fopen($abs.'.rot', 'wb');
        $keyId = $this->cipher->encryptStream($plainTmp, $out);
        fclose($out);
        fclose($plainTmp);
        rename($abs.'.rot', $abs);
        $doc->forceFill(['key_id' => $keyId])->save();

        return true;
    }
}
