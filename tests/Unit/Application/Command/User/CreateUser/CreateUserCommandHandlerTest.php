<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\User\CreateUser;

use App\Application\Command\User\CreateUser\CreateUserCommand;
use App\Application\Command\User\CreateUser\CreateUserCommandHandler;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const string ID  = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';
    private const string NOW = '2026-06-01T12:00:00+00:00';

    public function test_creates_the_user_with_the_given_id_and_logs(): void
    {
        $users   = new InMemoryUserRepository();
        $appLogger  = new SpyLogger();
        $handler = new CreateUserCommandHandler($users, new MockClock(self::NOW), $appLogger);

        $handler(new CreateUserCommand(self::ID, 'user@example.com', 'User Name'));

        $user = $users->findById(UserId::fromString(self::ID));
        $this->assertNotNull($user);
        $this->assertSame('user@example.com', $user->getEmail()->value());
        $this->assertSame('User Name', $user->getName()->value());
        $this->assertSame(self::NOW, $user->getCreatedAt()->format(\DATE_ATOM));
        $this->assertNull($user->getUpdatedAt());
        $this->assertTrue($appLogger->has('info', 'User created', ['user_id' => self::ID, 'user_email' => 'user@example.com']));
    }

    public function test_refuses_an_email_that_is_already_taken(): void
    {
        $users   = new InMemoryUserRepository(Mother::user(email: 'user@example.com'));
        $appLogger  = new SpyLogger();
        $handler = new CreateUserCommandHandler($users, new MockClock(self::NOW), $appLogger);

        $this->assertDomainError(
            UserAlreadyExistsException::ERROR_CODE,
            static fn () => $handler(new CreateUserCommand(self::ID, 'USER@example.com', 'Other Name')),
            ['email' => 'user@example.com'],
        );

        $this->assertCount(1, $users->findAll());
        $this->assertSame([], $appLogger->records);
    }
}
