<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\ServiceAccount;

use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use PHPUnit\Framework\TestCase;

final class ScopeCollectionTest extends TestCase
{
    public function test_deduplicates(): void
    {
        $c = ScopeCollection::fromStrings(['user.create', 'user.create', 'user.delete']);
        $this->assertCount(2, $c->toArray());
    }

    public function test_contains_and_set_equality(): void
    {
        $a = ScopeCollection::fromStrings(['a.read', 'b.write']);
        $b = ScopeCollection::fromStrings(['b.write', 'a.read']);
        $this->assertTrue($a->equals($b));
        $this->assertTrue($a->contains(new Scope('a.read')));
    }
}
