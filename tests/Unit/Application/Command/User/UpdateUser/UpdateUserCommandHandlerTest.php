<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\User\UpdateUser;

use App\Application\Command\User\UpdateUser\UpdateUserCommand;
use App\Application\Command\User\UpdateUser\UpdateUserCommandHandler;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Mother;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class UpdateUserCommandHandlerTest extends TestCase
{
    use AssertsDomainError;

    private const string NOW = '2030-01-01T10:00:00+00:00';

    private SpyLogger $appLogger;

    protected function setUp(): void
    {
        $this->appLogger = new SpyLogger();
    }

    public function test_updates_the_given_fields_and_logs(): void
    {
        $user  = Mother::user(email: 'jane@example.com', name: 'Jane Doe');
        $users = new InMemoryUserRepository($user);

        $view = $this->handler($users)(new UpdateUserCommand($user->getId()->value(), 'jane.smith@example.com', 'Jane Smith'));

        $this->assertSame('jane.smith@example.com', $user->getEmail()->value());
        $this->assertSame('Jane Smith', $user->getName()->value());
        $this->assertSame(self::NOW, (string) $user->getUpdatedAt());
        $this->assertSame('jane.smith@example.com', $view->email);
        $this->assertSame($user, $users->findByEmail($user->getEmail()));
        $this->assertTrue($this->appLogger->has('info', 'User updated', ['user_id' => $user->getId()->value()]));
    }

    public function test_null_fields_are_left_unchanged(): void
    {
        $user = Mother::user(email: 'jane@example.com', name: 'Jane Doe');

        $this->handler(new InMemoryUserRepository($user))(new UpdateUserCommand($user->getId()->value(), null, 'Jane Smith'));

        $this->assertSame('jane@example.com', $user->getEmail()->value());
        $this->assertSame('Jane Smith', $user->getName()->value());
    }

    public function test_keeping_the_own_email_is_not_a_conflict(): void
    {
        $user = Mother::user(email: 'jane@example.com');

        $this->handler(new InMemoryUserRepository($user))(new UpdateUserCommand($user->getId()->value(), 'JANE@example.com', null));

        $this->assertNull($user->getUpdatedAt(), 'Same email after normalisation: nothing changed');
    }

    public function test_refuses_an_email_that_belongs_to_another_user(): void
    {
        $user  = Mother::user(email: 'jane@example.com');
        $other = Mother::user(email: 'john@example.com');

        $handler = $this->handler(new InMemoryUserRepository($user, $other));

        $this->assertDomainError(
            UserAlreadyExistsException::ERROR_CODE,
            static fn () => $handler(new UpdateUserCommand($user->getId()->value(), 'john@example.com', 'Jane Smith')),
            ['email' => 'john@example.com'],
        );
        $this->assertSame('jane@example.com', $user->getEmail()->value());
        $this->assertSame([], $this->appLogger->records);
    }

    public function test_fails_when_the_user_does_not_exist(): void
    {
        $userId  = UserId::new()->value();
        $handler = $this->handler(new InMemoryUserRepository());

        $this->assertDomainError(UserNotFoundException::ERROR_CODE, static fn () => $handler(new UpdateUserCommand($userId, null, 'Some Name')), ['user_id' => $userId]);
    }

    private function handler(InMemoryUserRepository $users): UpdateUserCommandHandler
    {
        return new UpdateUserCommandHandler($users, new MockClock(self::NOW), $this->appLogger);
    }
}
