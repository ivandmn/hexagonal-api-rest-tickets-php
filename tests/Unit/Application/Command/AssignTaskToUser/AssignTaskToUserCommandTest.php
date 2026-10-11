<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\AssignTaskToUser;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserCommand;
use App\Domain\Exception\InvalidValueException;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class AssignTaskToUserCommandTest extends TestCase
{
    use AssertsDomainError;

    public function test_builds_the_value_objects(): void
    {
        $command = new AssignTaskToUserCommand('018f9f9a-aaaa-bbbb-cccc-000000000001', '018f9f9a-dddd-eeee-ffff-000000000002');

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', $command->getTaskId()->value());
        $this->assertSame('018f9f9a-dddd-eeee-ffff-000000000002', $command->getUserId()->value());
    }

    public function test_rejects_invalid_ids_when_built(): void
    {
        $this->assertFieldError('taskId', InvalidValueException::REQUIRED, static fn () => new AssignTaskToUserCommand('', '018f9f9a-dddd-eeee-ffff-000000000002'));
        $this->assertFieldError('userId', InvalidValueException::INVALID_FORMAT, static fn () => new AssignTaskToUserCommand('018f9f9a-aaaa-bbbb-cccc-000000000001', ' user '));
    }
}
