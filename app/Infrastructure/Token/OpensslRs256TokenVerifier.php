<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Auth\Result\VerifiedToken;
use App\Application\Port\SigningKeyProvider;
use App\Application\Port\TokenVerifier;
use App\Domain\Identity\Token\Exception\TokenInvalid;
use DateTimeImmutable;

/**
 * Verifies RS256 JWTs. The algorithm is pinned to RS256 (no alg-confusion / "none"), the key is
 * selected by kid, and verification uses the public key only.
 */
final readonly class OpensslRs256TokenVerifier implements TokenVerifier
{
    public function __construct(
        private SigningKeyProvider $keys,
        private string $issuer,
        private string $audience,
    ) {}

    public function verify(string $jwt, DateTimeImmutable $now): VerifiedToken
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw TokenInvalid::because('malformed');
        }
        [$headerSeg, $payloadSeg, $signatureSeg] = $parts;

        $header = Jwt::decodeSegment($headerSeg);
        if ($header === null || ($header['alg'] ?? null) !== 'RS256') {
            throw TokenInvalid::because('unsupported_alg');
        }

        $kid = is_string($header['kid'] ?? null) ? $header['kid'] : '';
        $pem = $this->keys->publicKeyPemForKid($kid);
        if ($pem === null) {
            throw TokenInvalid::because('unknown_kid');
        }

        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw TokenInvalid::because('bad_key');
        }

        $verified = openssl_verify($headerSeg.'.'.$payloadSeg, Jwt::base64UrlDecode($signatureSeg), $key, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw TokenInvalid::because('signature');
        }

        $claims = Jwt::decodeSegment($payloadSeg);
        if ($claims === null) {
            throw TokenInvalid::because('malformed');
        }

        $exp = is_int($claims['exp'] ?? null) ? $claims['exp'] : 0;
        if ($now->getTimestamp() >= $exp) {
            throw TokenInvalid::because('expired');
        }
        if (($claims['iss'] ?? null) !== $this->issuer || ($claims['aud'] ?? null) !== $this->audience) {
            throw TokenInvalid::because('claims');
        }

        return new VerifiedToken(
            subject: is_string($claims['sub'] ?? null) ? $claims['sub'] : '',
            jti: is_string($claims['jti'] ?? null) ? $claims['jti'] : '',
            claims: $claims,
            expiresAt: (new DateTimeImmutable('@'.$exp))->format(DATE_RFC3339),
        );
    }
}
