<?php

namespace Libinkk\OneAuth\Support;

class TotpService
{
    public function generateSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(20)), '+/', 'AB'), '=');
    }

    public function nowCode(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string
    {
        $time ??= time();
        $counter = intdiv($time, $period);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $chunk = substr($hash, $offset, 4);
        $value = unpack('N', $chunk)[1] & 0x7FFFFFFF;
        $mod = 10 ** $digits;

        return str_pad((string) ($value % $mod), $digits, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        for ($i = -$window; $i <= $window; $i++) {
            $timestamp = time() + ($i * 30);
            if (hash_equals($this->nowCode($secret, $timestamp), $code)) {
                return true;
            }
        }

        return false;
    }
}
