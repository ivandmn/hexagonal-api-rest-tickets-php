<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateUser;

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class CreateUserCommandTest extends TestCase
{
    use AssertsDomainError;

    public function test_builds_the_value_objects(): void
    {
        $command = new CreateUserCommand('User@Example.com', 'User Name');

        $this->assertSame('user@example.com', $command->getEmail()->value(), 'UserEmail normalises to lower case');
        $this->assertSame('User Name', $command->getName()->value());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new CreateUserCommand('', 'User Name'));
        $this->assertDomainError(InvalidValueException::TOO_SHORT, static fn () => new CreateUserCommand('user@example.com', ''), ['min' => UserName::MIN_LENGTH]);
    }
}
