<?php

namespace App\Services\Files;

/** فحص محلي عبر clamd (بروتوكول INSTREAM) على الشبكة الداخلية — لا تُرسل الملفات لأي خدمة خارجية */
class ClamAvScanner implements MalwareScanner
{
    public function __construct(private string $host, private int $port, private int $timeout) {}

    public function scan(callable $feed): array
    {
        $sock = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $err, 5);
        if (! $sock) {
            return ['status' => 'error', 'engine' => 'clamav', 'signature' => 'unreachable'];
        }
        stream_set_timeout($sock, $this->timeout);
        fwrite($sock, "zINSTREAM\0");
        $feed(function (string $chunk) use ($sock) {
            foreach (str_split($chunk, 8192) as $part) {
                if ($part === '') {
                    continue;
                }
                fwrite($sock, pack('N', strlen($part)).$part);
            }
        });
        fwrite($sock, pack('N', 0));
        $resp = trim((string) stream_get_contents($sock), "\0\r\n ");
        fclose($sock);
        if (str_ends_with($resp, 'OK') && ! str_contains($resp, 'FOUND')) {
            return ['status' => 'clean', 'engine' => 'clamav'];
        }
        if (str_contains($resp, 'FOUND')) {
            return ['status' => 'infected', 'engine' => 'clamav', 'signature' => trim(str_replace(['stream:', 'FOUND'], '', $resp))];
        }

        return ['status' => 'error', 'engine' => 'clamav', 'signature' => mb_substr($resp, 0, 80)];
    }
}
