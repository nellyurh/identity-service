<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Identity\Token\Exception\TokenInvalid;
use App\Infrastructure\Token\ConfigSigningKeyProvider;
use App\Infrastructure\Token\OpensslRs256TokenIssuer;
use App\Infrastructure\Token\OpensslRs256TokenVerifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TokenServiceTest extends TestCase
{
    private ConfigSigningKeyProvider $keys;

    private OpensslRs256TokenIssuer $issuer;

    private OpensslRs256TokenVerifier $verifier;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource === false) {
            throw new RuntimeException('could not generate test key');
        }
        $privatePem = '';
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);
        $publicPem = is_array($details) && isset($details['key']) ? (string) $details['key'] : '';

        $this->keys = new ConfigSigningKeyProvider((string) $privatePem, $publicPem, 'test-kid');
        $this->issuer = new OpensslRs256TokenIssuer($this->keys, 'unero.identity-service', 'unero-internal', 900);
        $this->verifier = new OpensslRs256TokenVerifier($this->keys, 'unero.identity-service', 'unero-internal');
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    public function test_issue_then_verify_round_trip(): void
    {
        $issued = $this->issuer->issueAccessToken('01J000000000000000000USER', ['token_use' => 'access'], $this->now);

        $verified = $this->verifier->verify($issued->token, $this->now);

        $this->assertSame('01J000000000000000000USER', $verified->subject);
        $this->assertSame($issued->jti, $verified->jti);
        $this->assertSame('access', $verified->claims['token_use']);
        $this->assertSame(900, $issued->expiresIn);
        $this->assertSame('Bearer', $issued->tokenType);
    }

    public function test_tampered_token_is_rejected(): void
    {
        $issued = $this->issuer->issueAccessToken('sub', [], $this->now);
        [$h, $p, $s] = explode('.', $issued->token);
        $tampered = $h.'.'.$p.'x.'.$s;

        $this->expectException(TokenInvalid::class);
        $this->verifier->verify($tampered, $this->now);
    }

    public function test_expired_token_is_rejected(): void
    {
        $issued = $this->issuer->issueAccessToken('sub', [], $this->now);

        $this->expectException(TokenInvalid::class);
        $this->verifier->verify($issued->token, $this->now->modify('+901 seconds'));
    }

    public function test_wrong_audience_is_rejected(): void
    {
        $issued = $this->issuer->issueAccessToken('sub', [], $this->now);
        $otherAudience = new OpensslRs256TokenVerifier($this->keys, 'unero.identity-service', 'someone-else');

        $this->expectException(TokenInvalid::class);
        $otherAudience->verify($issued->token, $this->now);
    }

    public function test_jwks_exposes_the_public_key(): void
    {
        $jwks = $this->keys->jwks();

        $this->assertCount(1, $jwks);
        $this->assertSame('test-kid', $jwks[0]['kid']);
        $this->assertSame('RSA', $jwks[0]['kty']);
        $this->assertSame('RS256', $jwks[0]['alg']);
        $this->assertNotEmpty($jwks[0]['n']);
        $this->assertNotEmpty($jwks[0]['e']);
    }
}
