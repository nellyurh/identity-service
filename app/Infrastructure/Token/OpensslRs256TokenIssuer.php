<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Application\Auth\Result\IssuedAccessToken;
use App\Application\Port\SigningKeyProvider;
use App\Application\Port\TokenIssuer;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\Uid\Ulid;

/** Issues RS256-signed JWT access tokens using PHP's openssl (no external JWT dependency). */
final readonly class OpensslRs256TokenIssuer implements TokenIssuer
{
    public function __construct(
        private SigningKeyProvider $keys,
        private string $issuer,
        private string $audience,
        private int $ttlSeconds,
    ) {}

    public function issueAccessToken(string $subject, array $claims, DateTimeImmutable $now): IssuedAccessToken
    {
        $pem = $this->keys->privateKeyPem();
        if ($pem === '') {
            throw new RuntimeException('No signing key configured (IDENTITY_JWT_PRIVATE_KEY).');
        }

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Configured JWT private key is not a valid PEM.');
        }

        $jti = (string) new Ulid;
        $issuedAt = $now->getTimestamp();
        $expiresAt = $issuedAt + $this->ttlSeconds;

        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $this->keys->currentKid()];
        $payload = array_merge($claims, [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => $subject,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => $jti,
        ]);

        $signingInput = Jwt::encodeSegment($header).'.'.Jwt::encodeSegment($payload);

        $signature = '';
        if (openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256) === false) {
            throw new RuntimeException('Failed to sign access token.');
        }

        $token = $signingInput.'.'.Jwt::base64UrlEncode($signature);

        return new IssuedAccessToken(
            token: $token,
            jti: $jti,
            expiresIn: $this->ttlSeconds,
            expiresAt: (new DateTimeImmutable('@'.$expiresAt))->format(DATE_RFC3339),
        );
    }
}
