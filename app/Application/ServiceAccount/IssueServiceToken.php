<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TokenIssuer;
use App\Application\ServiceAccount\Command\IssueServiceTokenCommand;
use App\Application\ServiceAccount\Result\ServiceTokenResult;
use App\Domain\Identity\ServiceAccount\Exception\InvalidClientCredentials;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use InvalidArgumentException;

/**
 * Client-credentials grant: authenticate a service account by its name (client_id) and secret, and
 * mint a short-lived RS256 service token carrying the account's scopes (token_use=service).
 *
 * No enumeration: a malformed/unknown client_id, a wrong secret, and a disabled account all fail
 * with the same InvalidClientCredentials. The secret comparison runs against a dummy hash when the
 * account is absent so timing does not reveal existence.
 */
final readonly class IssueServiceToken
{
    private const string DUMMY_HASH = 'unknown-service-account-placeholder';

    public function __construct(
        private ServiceAccountRepository $accounts,
        private TokenIssuer $tokens,
        private AuditWriter $audit,
        private Clock $clock,
    ) {}

    public function handle(IssueServiceTokenCommand $c): ServiceTokenResult
    {
        $account = $this->resolve($c->clientId);

        $presented = HashedSecret::fromHash(hash('sha256', $c->clientSecret));
        $stored = $account?->secretHash() ?? HashedSecret::fromHash(hash('sha256', self::DUMMY_HASH));
        $secretMatches = $stored->equals($presented);

        if (! $account instanceof ServiceAccount || ! $secretMatches || ! $account->status()->canAuthenticate()) {
            throw InvalidClientCredentials::create();
        }

        $now = $this->clock->now();
        $scopes = $account->scopes()->toArray();

        $issued = $this->tokens->issueAccessToken(
            $account->id->value,
            ['token_use' => 'service', 'scopes' => $scopes],
            $now,
        );

        $this->audit->record(
            'service_account.token_issued',
            $account->id->value,
            'service_account:'.$account->id->value,
            [],
            ['jti' => $issued->jti, 'scopes' => $scopes],
            $c->requestId,
            null,
        );

        return new ServiceTokenResult($issued->token, $issued->tokenType, $issued->expiresIn, $scopes);
    }

    private function resolve(string $clientId): ?ServiceAccount
    {
        try {
            $name = new ServiceName($clientId);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->accounts->findByName($name);
    }
}
