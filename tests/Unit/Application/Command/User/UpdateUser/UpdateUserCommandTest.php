<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\User\UpdateUser;

use App\Application\Command\User\UpdateUser\UpdateUserCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class UpdateUserCommandTest extends TestCase
{
    use AssertsDomainError;

    private const string ID = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';

    public function test_builds_the_value_objects(): void
    {
        $command = new UpdateUserCommand(self::ID, 'User@Example.com', 'User Name');

        $this->assertTrue($command->getUserId()->equals(UserId::fromString(self::ID)));
        $this->assertSame('user@example.com', $command->getEmail()?->value());
        $this->assertSame('User Name', $command->getName()?->value());
    }

    public function test_null_fields_stay_null(): void
    {
        $command = new UpdateUserCommand(self::ID, null, null);

        $this->assertNull($command->getEmail());
        $this->assertNull($command->getName());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertFieldError('userId', InvalidValueException::INVALID_FORMAT, static fn () => new UpdateUserCommand('nope', null, null));
        $this->assertFieldError('email', InvalidValueException::INVALID_FORMAT, static fn () => new UpdateUserCommand(self::ID, 'not-an-email', null));
        $this->assertFieldError('name', InvalidValueException::TOO_SHORT, static fn () => new UpdateUserCommand(self::ID, null, 'ab'), ['min' => UserName::MIN_LENGTH]);
    }
}
