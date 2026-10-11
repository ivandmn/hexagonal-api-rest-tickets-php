<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineUserRepositoryTest extends KernelTestCase
{
    /**
     * Two concurrent sign-ups can both pass the handler's findByEmail() check: the unique index
     * must then surface as the domain exception (409), not as a database error (500).
     */
    public function test_saving_a_second_user_with_the_same_email_throws_the_domain_exception(): void
    {
        /** @var UserRepositoryInterface $users */
        $users = static::getContainer()->get(UserRepositoryInterface::class);

        $users->save(User::create(UserId::new(), UserEmail::fromString('race@example.com'), UserName::fromString('First User'), new \DateTimeImmutable()));

        $this->expectException(UserAlreadyExistsException::class);
        $users->save(User::create(UserId::new(), UserEmail::fromString('race@example.com'), UserName::fromString('Second User'), new \DateTimeImmutable()));
    }

    public function test_changes_to_a_user_reach_the_database(): void
    {
        /** @var UserRepositoryInterface $users */
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = User::create(UserId::new(), UserEmail::fromString('before@example.com'), UserName::fromString('Before Name'), new \DateTimeImmutable('2026-01-01T10:00:00Z'));
        $users->save($user);

        $now = new \DateTimeImmutable('2026-02-01T12:30:00+02:00');
        $user->changeEmail(UserEmail::fromString('after@example.com'), $now);
        $user->changeName(UserName::fromString('After Name'), $now);
        $users->save($user);
        $entityManager->clear();

        $loaded = $users->findById($user->getId());

        $this->assertNotNull($loaded);
        $this->assertSame('after@example.com', $loaded->getEmail()->value());
        $this->assertSame('After Name', $loaded->getName()->value());
        $this->assertSame('2026-01-01T10:00:00+00:00', (string) $loaded->getCreatedAt());
        $this->assertSame('2026-02-01T10:30:00+00:00', (string) $loaded->getUpdatedAt());
    }

    public function test_changing_to_a_taken_email_throws_the_domain_exception(): void
    {
        /** @var UserRepositoryInterface $users */
        $users = static::getContainer()->get(UserRepositoryInterface::class);

        $users->save(User::create(UserId::new(), UserEmail::fromString('taken@example.com'), UserName::fromString('First User'), new \DateTimeImmutable()));
        $other = User::create(UserId::new(), UserEmail::fromString('free@example.com'), UserName::fromString('Second User'), new \DateTimeImmutable());
        $users->save($other);

        $other->changeEmail(UserEmail::fromString('taken@example.com'), new \DateTimeImmutable());

        $this->expectException(UserAlreadyExistsException::class);
        $users->save($other);
    }
}
