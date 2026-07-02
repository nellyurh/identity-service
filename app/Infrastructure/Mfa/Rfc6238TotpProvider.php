<?php

declare(strict_types=1);

namespace App\Infrastructure\Mfa;

use App\Application\Port\TotpProvider;
use DateTimeImmutable;

/**
 * Self-contained RFC 6238 (TOTP) / RFC 4226 (HOTP) implementation over HMAC-SHA1, with RFC 4648
 * base32 for secret encoding. No third-party dependency. Verified against the RFC 6238 test vectors.
 */
final readonly class Rfc6238TotpProvider implements TotpProvider
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private string $issuer,
        private int $period = 30,
        private int $digits = 6,
        private int $window = 1,
    ) {}

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(string $secret, string $accountName): string
    {
        $label = rawurlencode($this->issuer).':'.rawurlencode($accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $this->issuer,
            'algorithm' => 'SHA1',
            'digits' => $this->digits,
            'period' => $this->period,
        ]);

        return 'otpauth://totp/'.$label.'?'.$query;
    }

    public function verify(string $secret, string $code, DateTimeImmutable $now): bool
    {
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $counter = intdiv($now->getTimestamp(), $this->period);
        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            if (hash_equals($this->hotp($key, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0, $counter); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($bytes);

        for ($i = 0; $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $out .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1F];
            }
        }
        if ($bitsLeft > 0) {
            $out .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $out;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim($secret, '='));
        $out = '';
        $buffer = 0;
        $bitsLeft = 0;
        $length = strlen($secret);

        for ($i = 0; $i < $length; $i++) {
            $pos = strpos(self::ALPHABET, $secret[$i]);
            if ($pos === false) {
                return '';
            }
            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $out .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $out;
    }
}
