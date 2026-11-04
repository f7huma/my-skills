<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** يُرسل للطابور دوريًا؛ تنفيذه دليل على أن العامل يعمل */
class WorkerHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        cache()->put('ops:worker-heartbeat', now()->timestamp, 3600);
    }
}
