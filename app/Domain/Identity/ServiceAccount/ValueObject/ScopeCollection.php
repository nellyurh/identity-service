<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount\ValueObject;

/**
 * An immutable, de-duplicated set of scopes. Order is not significant; equality is by set
 * membership.
 */
final readonly class ScopeCollection
{
    /** @var list<Scope> */
    public array $scopes;

    public function __construct(Scope ...$scopes)
    {
        $unique = [];
        foreach ($scopes as $scope) {
            $unique[$scope->value] = $scope;
        }
        $this->scopes = array_values($unique);
    }

    /** @param list<string> $values */
    public static function fromStrings(array $values): self
    {
        return new self(...array_map(static fn (string $v): Scope => new Scope($v), $values));
    }

    public function contains(Scope $scope): bool
    {
        foreach ($this->scopes as $s) {
            if ($s->equals($scope)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return array_map(static fn (Scope $s): string => $s->value, $this->scopes);
    }

    public function equals(self $other): bool
    {
        $a = $this->toArray();
        $b = $other->toArray();
        sort($a);
        sort($b);

        return $a === $b;
    }
}
