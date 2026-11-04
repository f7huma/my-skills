<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Files\FileVault;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * فحص المرفق محليًا. عند تعطل الفحص يبقى الملف محجوبًا وتُعاد المحاولة بتباعد متزايد
 * حتى الحد الأقصى، ثم يلتقطه الفحص المجدول لاحقًا (files:rescan-pending).
 */
class ScanDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;


    public function __construct(public int $documentId)
    {
        $this->onQueue('scans');
    }

    public function middleware(): array
    {
        return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('scan-'.$this->documentId))->expireAfter(600)->dontRelease()];
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(FileVault $vault): void
    {
        $doc = Document::find($this->documentId);
        if (! $doc || $doc->scan_status !== 'pending' && $doc->scan_status !== 'error') {
            return;
        }
        $status = $vault->scan($doc);
        if ($status === 'error' && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 1800);
        }
    }
}
