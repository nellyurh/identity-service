<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Token;

use App\Application\Auth\Command\IntrospectCommand;
use App\Application\Auth\IntrospectToken;
use App\Application\Auth\Result\VerifiedToken;
use App\Application\Port\TokenVerifier;
use App\Domain\Identity\Token\Exception\TokenInvalid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTokenBlacklist;
use Tests\Support\FixedClock;

final class IntrospectTokenTest extends TestCase
{
    private FixedClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock(new DateTimeImmutable('2026-07-02T10:00:00+00:00'));
    }

    private function verifierReturning(VerifiedToken $token): TokenVerifier
    {
        return new readonly class($token) implements TokenVerifier
        {
            public function __construct(private VerifiedToken $token) {}

            public function verify(string $jwt, DateTimeImmutable $now): VerifiedToken
            {
                return $this->token;
            }
        };
    }

    private function verifierThrowing(): TokenVerifier
    {
        return new class implements TokenVerifier
        {
            public function verify(string $jwt, DateTimeImmutable $now): VerifiedToken
            {
                throw TokenInvalid::because('signature');
            }
        };
    }

    public function test_valid_unblacklisted_token_is_active(): void
    {
        $verified = new VerifiedToken('user-1', 'jti-1', ['token_use' => 'access'], '2026-07-02T10:15:00+00:00');
        $sut = new IntrospectToken($this->verifierReturning($verified), new FakeTokenBlacklist, $this->clock);

        $result = $sut->handle(new IntrospectCommand('jwt', 'req-1'));

        $this->assertTrue($result->active);
        $this->assertSame('user-1', $result->subject);
        $this->assertSame('jti-1', $result->jti);
        $this->assertSame('access', $result->tokenUse);
    }

    public function test_invalid_token_is_inactive(): void
    {
        $sut = new IntrospectToken($this->verifierThrowing(), new FakeTokenBlacklist, $this->clock);

        $result = $sut->handle(new IntrospectCommand('jwt', 'req-1'));

        $this->assertFalse($result->active);
        $this->assertNull($result->subject);
    }

    public function test_blacklisted_token_is_inactive(): void
    {
        $verified = new VerifiedToken('user-1', 'jti-1', ['token_use' => 'access'], '2026-07-02T10:15:00+00:00');
        $blacklist = new FakeTokenBlacklist;
        $blacklist->blacklist('jti-1', new DateTimeImmutable('2026-07-02T10:15:00+00:00'));

        $sut = new IntrospectToken($this->verifierReturning($verified), $blacklist, $this->clock);

        $result = $sut->handle(new IntrospectCommand('jwt', 'req-1'));

        $this->assertFalse($result->active);
    }
}
