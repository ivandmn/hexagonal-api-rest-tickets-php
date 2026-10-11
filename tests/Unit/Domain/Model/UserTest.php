<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Model\User;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UserTest extends TestCase
{
    public function test_create_sets_fields_and_creation_time(): void
    {
        $id    = UserId::new();
        $email = UserEmail::fromString('test@example.com');
        $name  = UserName::fromString('Test User');
        $now   = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');

        $user = User::create($id, $email, $name, $now);

        $this->assertSame($id, $user->getId());
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($name, $user->getName());
        $this->assertSame($now, $user->getCreatedAt());
        $this->assertNull($user->getUpdatedAt());
    }

    public function test_equals_true_when_same_id_even_if_other_fields_differ(): void
    {
        $uuid = RamseyUuid::uuid4()->toString();
        $id1  = UserId::fromString($uuid);
        $id2  = UserId::fromString($uuid);

        $u1 = User::create($id1, UserEmail::fromString('one@example.com'), UserName::fromString('User One'), new \DateTimeImmutable('2030-01-01T00:00:00+00:00'));
        $u2 = User::create($id2, UserEmail::fromString('two@example.com'), UserName::fromString('User Two'), new \DateTimeImmutable('2035-01-01T00:00:00+00:00'));

        $this->assertTrue($u1->equals($u2));
    }

    public function test_equals_false_when_different_id(): void
    {
        $u1 = User::create(UserId::new(), UserEmail::fromString('a@example.com'), UserName::fromString('Alpha User'), new \DateTimeImmutable());
        $u2 = User::create(UserId::new(), UserEmail::fromString('a@example.com'), UserName::fromString('Alpha User'), new \DateTimeImmutable());

        $this->assertFalse($u1->equals($u2));
    }
}
