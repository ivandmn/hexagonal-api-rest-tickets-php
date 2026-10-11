<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
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
}
