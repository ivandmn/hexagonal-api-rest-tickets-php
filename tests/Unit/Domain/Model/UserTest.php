<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Model;

use App\Domain\Event\User\UserDeleted;
use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\Model\User;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UserTest extends TestCase
{
    use AssertsDomainError;

    private const string LATER = '2030-01-02T08:30:00+00:00';

    public function test_create_sets_fields_and_creation_time(): void
    {
        $id    = UserId::new();
        $email = UserEmail::fromString('test@example.com');
        $name  = UserName::fromString('Test User');

        $user = User::create($id, $email, $name, new \DateTimeImmutable('2030-01-01T12:00:00.5+02:00'));

        $this->assertSame($id, $user->getId());
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($name, $user->getName());
        $this->assertSame('2030-01-01T10:00:00+00:00', (string) $user->getCreatedAt(), 'UTC, whole seconds');
        $this->assertNull($user->getUpdatedAt());
        $this->assertSame([], $user->pullDomainEvents());
    }

    public function test_change_name_sets_updated_at(): void
    {
        $user = Mother::user(name: 'Jane Doe');

        $user->changeName(UserName::fromString('Jane Smith'), new \DateTimeImmutable(self::LATER));

        $this->assertSame('Jane Smith', $user->getName()->value());
        $this->assertSame(self::LATER, (string) $user->getUpdatedAt());
    }

    public function test_change_email_sets_updated_at(): void
    {
        $user = Mother::user(email: 'jane@example.com');

        $user->changeEmail(UserEmail::fromString('jane.smith@example.com'), new \DateTimeImmutable(self::LATER));

        $this->assertSame('jane.smith@example.com', $user->getEmail()->value());
        $this->assertSame(self::LATER, (string) $user->getUpdatedAt());
    }

    public function test_same_values_change_nothing(): void
    {
        $user = Mother::user(email: 'jane@example.com', name: 'Jane Doe');

        $user->changeName(UserName::fromString('Jane Doe'), new \DateTimeImmutable(self::LATER));
        $user->changeEmail(UserEmail::fromString('jane@example.com'), new \DateTimeImmutable(self::LATER));

        $this->assertNull($user->getUpdatedAt());
    }

    public function test_delete_records_user_deleted(): void
    {
        $user = Mother::user();

        $user->delete(0, 3, new \DateTimeImmutable(self::LATER));

        $events = $user->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserDeleted::class, $events[0]);
        $this->assertSame('user.deleted', $events[0]->eventName());
        $this->assertSame(self::LATER, (string) $events[0]->occurredOn());
        $this->assertSame(['user_id' => $user->getId()->value(), 'deleted_tasks' => 3], $events[0]->payload());
    }

    public function test_delete_is_refused_while_tasks_are_unfinished(): void
    {
        $user   = Mother::user();
        $userId = $user->getId()->value();

        $this->assertDomainError(
            UserDeletionNotAllowedException::ERROR_CODE,
            static fn () => $user->delete(2, 1, new \DateTimeImmutable(self::LATER)),
            ['user_id' => $userId, 'unfinished_tasks' => 2],
        );
        $this->assertSame([], $user->pullDomainEvents());
    }

    public function test_equals_true_when_same_id_even_if_other_fields_differ(): void
    {
        $uuid = RamseyUuid::uuid4()->toString();

        $u1 = User::create(UserId::fromString($uuid), UserEmail::fromString('one@example.com'), UserName::fromString('User One'), new \DateTimeImmutable('2030-01-01T00:00:00+00:00'));
        $u2 = User::create(UserId::fromString($uuid), UserEmail::fromString('two@example.com'), UserName::fromString('User Two'), new \DateTimeImmutable('2035-01-01T00:00:00+00:00'));

        $this->assertTrue($u1->equals($u2));
    }

    public function test_equals_false_when_different_id(): void
    {
        $u1 = User::create(UserId::new(), UserEmail::fromString('a@example.com'), UserName::fromString('Alpha User'), new \DateTimeImmutable());
        $u2 = User::create(UserId::new(), UserEmail::fromString('a@example.com'), UserName::fromString('Alpha User'), new \DateTimeImmutable());

        $this->assertFalse($u1->equals($u2));
    }
}
