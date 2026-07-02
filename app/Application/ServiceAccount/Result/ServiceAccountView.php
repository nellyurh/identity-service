<?php

declare(strict_types=1);

namespace App\Application\ServiceAccount\Result;

use App\Domain\Identity\ServiceAccount\ServiceAccount;

final readonly class ServiceAccountView
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public array $scopes,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromAccount(ServiceAccount $account): self
    {
        return new self(
            id: $account->id->value,
            name: $account->name()->value,
            status: $account->status()->value,
            scopes: $account->scopes()->toArray(),
            createdAt: $account->createdAt()->format(DATE_RFC3339),
            updatedAt: $account->updatedAt()->format(DATE_RFC3339),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'scopes' => $this->scopes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
