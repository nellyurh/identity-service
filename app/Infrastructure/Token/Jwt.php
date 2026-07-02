<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

/** Minimal JWS helpers: URL-safe base64 and compact-serialization encode/decode. */
final class Jwt
{
    public static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $encoded): string
    {
        $padded = str_pad($encoded, (int) (ceil(strlen($encoded) / 4) * 4), '=', STR_PAD_RIGHT);
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    /** @param array<string,mixed> $data */
    public static function encodeSegment(array $data): string
    {
        return self::base64UrlEncode((string) json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string,mixed>|null */
    public static function decodeSegment(string $segment): ?array
    {
        $decoded = json_decode(self::base64UrlDecode($segment), true);

        return is_array($decoded) ? $decoded : null;
    }
}
