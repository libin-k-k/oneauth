<?php

namespace Libinkk\OneAuth\Support;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function nowCode(string $secret, ?int $time = null, int $digits = 6, int $period = 30): string
    {
        $time ??= time();
        $counter = intdiv($time, $period);
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);
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

    public function otpauthUri(string $secret, string $accountName, ?string $issuer = null): string
    {
        $issuer ??= (string) config('oneauth.two_factor.totp_issuer', 'OneAuth');
        $label = rawurlencode($issuer . ':' . $accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    protected function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    protected function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $binary = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $data = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $data .= chr(bindec($chunk));
            }
        }

        return $data;
    }
}
