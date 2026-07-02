<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Token;

use App\Domain\Identity\Token\Event\TokenIssued;
use App\Domain\Identity\Token\Event\TokenRevoked;
use App\Domain\Identity\Token\Exception\RefreshTokenInvalid;
use App\Domain\Identity\Token\Exception\TokenReuseDetected;
use App\Domain\Identity\Token\RefreshToken;
use App\Domain\Identity\Token\RevocationReason;
use App\Domain\Identity\Token\ValueObject\FamilyId;
use App\Domain\Identity\Token\ValueObject\RefreshTokenId;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class RefreshTokenTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function id(): RefreshTokenId
    {
        return new RefreshTokenId((string) new Ulid);
    }

    private function active(): RefreshToken
    {
        return RefreshToken::reconstitute(
            $this->id(),
            new UserId((string) new Ulid),
            new FamilyId((string) new Ulid),
            hash('sha256', 'secret-1'),
            'jti-1',
            $this->now->modify('+30 days'),
            $this->now,
            null,
            null,
            null,
        );
    }

    public function test_issue_records_token_issued(): void
    {
        $token = RefreshToken::issue(
            $this->id(),
            new UserId((string) new Ulid),
            new FamilyId((string) new Ulid),
            hash('sha256', 'secret'),
            'jti-1',
            $this->now->modify('+30 days'),
            $this->now,
        );

        $events = $token->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TokenIssued::class, $events[0]);
        $this->assertSame(
            ['user_id', 'family_id', 'access_jti', 'occurred_at'],
            array_keys($events[0]->payload()),
        );
    }

    public function test_rotate_closes_current_and_returns_successor_in_same_family(): void
    {
        $current = $this->active();
        $successorId = $this->id();

        $successor = $current->rotate($successorId, hash('sha256', 'secret-2'), 'jti-2', $this->now->modify('+30 days'), $this->now);

        $this->assertSame($this->now, $current->rotatedAt());
        $this->assertNotNull($current->replacedBy());
        $this->assertSame($successorId->value, $current->replacedBy()?->value);
        $this->assertTrue($successor->familyId()->equals($current->familyId()));
        $this->assertSame('jti-2', $successor->accessJti());

        $this->assertSame([], $current->releaseEvents());
        $successorEvents = $successor->releaseEvents();
        $this->assertCount(1, $successorEvents);
        $this->assertInstanceOf(TokenIssued::class, $successorEvents[0]);
    }

    public function test_presenting_a_rotated_token_is_reuse(): void
    {
        $current = $this->active();
        $current->rotate($this->id(), hash('sha256', 'secret-2'), 'jti-2', $this->now->modify('+30 days'), $this->now);

        $this->expectException(TokenReuseDetected::class);
        $current->assertUsable($this->now);
    }

    public function test_revoked_token_is_invalid(): void
    {
        $token = RefreshToken::reconstitute(
            $this->id(),
            new UserId((string) new Ulid),
            new FamilyId((string) new Ulid),
            hash('sha256', 'secret'),
            'jti-1',
            $this->now->modify('+30 days'),
            $this->now,
            null,
            null,
            $this->now,
        );

        $this->expectException(RefreshTokenInvalid::class);
        $token->assertUsable($this->now);
    }

    public function test_expired_token_is_invalid(): void
    {
        $token = RefreshToken::reconstitute(
            $this->id(),
            new UserId((string) new Ulid),
            new FamilyId((string) new Ulid),
            hash('sha256', 'secret'),
            'jti-1',
            $this->now->modify('-1 second'),
            $this->now,
            null,
            null,
            null,
        );

        $this->expectException(RefreshTokenInvalid::class);
        $token->assertUsable($this->now);
    }

    public function test_revoke_records_token_revoked_once(): void
    {
        $token = $this->active();

        $token->revoke(RevocationReason::Logout, $this->now);
        $token->revoke(RevocationReason::Logout, $this->now);

        $events = $token->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TokenRevoked::class, $events[0]);
        $this->assertSame('logout', $events[0]->payload()['reason']);
    }
}
