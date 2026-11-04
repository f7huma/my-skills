<?php

namespace App\Services\Files;

use RuntimeException;

/**
 * تشفير الملفات على مستوى الملف (XChaCha20-Poly1305 secretstream من libsodium) بمفتاح منفصل عن APP_KEY.
 * الصيغة: "HMF1" | key_id(8) | header(24) | [len(4) | chunk]...
 */
class FileCipher
{
    private const MAGIC = 'HMF1';

    private const CHUNK = 65536;

    /** @var array<string,string> key_id => raw key */
    private array $keys = [];

    private string $currentId;

    public function __construct(?string $current, array $previous = [])
    {
        if (! $current) {
            throw new RuntimeException('FILES_ENCRYPTION_KEY is not configured');
        }
        foreach (array_merge([$current], $previous) as $i => $b64) {
            $raw = base64_decode(preg_replace('/^base64:/', '', trim($b64)), true);
            if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                throw new RuntimeException('Invalid file encryption key');
            }
            $id = self::keyId($raw);
            $this->keys[$id] = $raw;
            if ($i === 0) {
                $this->currentId = $id;
            }
        }
    }

    public static function keyId(string $raw): string
    {
        return substr(hash('sha256', 'humaidan-file-key:'.$raw), 0, 8);
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public function currentKeyId(): string
    {
        return $this->currentId;
    }

    /** @param resource $in @param resource $out */
    public function encryptStream($in, $out): string
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->keys[$this->currentId]);
        fwrite($out, self::MAGIC.$this->currentId.$header);
        $buf = fread($in, self::CHUNK);
        while (true) {
            $next = feof($in) ? '' : fread($in, self::CHUNK);
            $last = ($next === '' || $next === false);
            $tag = $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $buf, '', $tag);
            fwrite($out, pack('N', strlen($c)).$c);
            if ($last) {
                break;
            }
            $buf = $next;
        }

        return $this->currentId;
    }

    /**
     * @param  resource  $in
     * @param  callable(string):void  $sink
     */
    public function decryptStream($in, callable $sink): void
    {
        $magic = fread($in, 4);
        if ($magic !== self::MAGIC) {
            throw new RuntimeException('Unknown file format');
        }
        $id = fread($in, 8);
        if (! isset($this->keys[$id])) {
            throw new RuntimeException('Encryption key not available for this file');
        }
        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->keys[$id]);
        $final = false;
        while (! $final) {
            $lenRaw = fread($in, 4);
            if (strlen((string) $lenRaw) !== 4) {
                throw new RuntimeException('Truncated encrypted file');
            }
            $len = unpack('N', $lenRaw)[1];
            $c = '';
            while (strlen($c) < $len && ! feof($in)) {
                $c .= fread($in, $len - strlen($c));
            }
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $c);
            if ($res === false) {
                throw new RuntimeException('File integrity check failed');
            }
            [$plain, $tag] = $res;
            $sink($plain);
            $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
    }

    public function fileKeyId(string $path): ?string
    {
        $h = fopen($path, 'rb');
        $head = fread($h, 12);
        fclose($h);

        return str_starts_with($head, self::MAGIC) ? substr($head, 4, 8) : null;
    }
}
