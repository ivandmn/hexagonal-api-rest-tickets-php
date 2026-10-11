<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\User\CreateUser;

use App\Application\Command\User\CreateUser\CreateUserCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class CreateUserCommandTest extends TestCase
{
    use AssertsDomainError;

    private const string ID = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';

    public function test_builds_the_value_objects(): void
    {
        $command = new CreateUserCommand(self::ID, 'User@Example.com', 'User Name');

        $this->assertTrue($command->getUserId()->equals(UserId::fromString(self::ID)));
        $this->assertSame('user@example.com', $command->getEmail()->value(), 'UserEmail normalises to lower case');
        $this->assertSame('User Name', $command->getName()->value());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertFieldError('userId', InvalidValueException::INVALID_FORMAT, static fn () => new CreateUserCommand('nope', 'user@example.com', 'User Name'));
        $this->assertFieldError('email', InvalidValueException::REQUIRED, static fn () => new CreateUserCommand(self::ID, '', 'User Name'));
        $this->assertFieldError('email', InvalidValueException::INVALID_FORMAT, static fn () => new CreateUserCommand(self::ID, 'not-an-email', 'User Name'));
        $this->assertFieldError('name', InvalidValueException::REQUIRED, static fn () => new CreateUserCommand(self::ID, 'user@example.com', '   '));
        $this->assertFieldError('name', InvalidValueException::TOO_SHORT, static fn () => new CreateUserCommand(self::ID, 'user@example.com', 'ab'), ['min' => UserName::MIN_LENGTH]);
    }
}
