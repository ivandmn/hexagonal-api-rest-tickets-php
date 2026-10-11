<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\CreateTask;

use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\FutureDate;
use PHPUnit\Framework\TestCase;

final class CreateTaskCommandTest extends TestCase
{
    use AssertsDomainError;

    private const string ID = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';

    public function test_builds_the_value_objects(): void
    {
        $dueDate = FutureDate::on()->format(TaskDueDate::FORMAT);
        $command = new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'medium', $dueDate);

        $this->assertTrue($command->getTaskId()->equals(TaskId::fromString(self::ID)));
        $this->assertSame('Test Title', $command->getTitle()->value());
        $this->assertSame('Test Description', $command->getDescription()->value());
        $this->assertSame(TaskPriority::MEDIUM, $command->getPriority());
        $this->assertSame($dueDate, (string) $command->getDueDate());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertFieldError('taskId', InvalidValueException::INVALID_FORMAT, static fn () => new CreateTaskCommand('not-a-uuid', 'Test Title', 'D', 'low', null));
        $this->assertFieldError('title', InvalidValueException::TOO_SHORT, static fn () => new CreateTaskCommand(self::ID, 'abc', 'D', 'low', null));
        $this->assertFieldError('priority', InvalidValueException::INVALID_CHOICE, static fn () => new CreateTaskCommand(self::ID, 'Test Title', 'D', 'urgent', null));
    }

    public function test_a_past_due_date_is_accepted_here_because_the_rule_needs_the_clock(): void
    {
        // "Not in the past" is checked by Task::create with the injected clock, not by the message.
        $command = new CreateTaskCommand(self::ID, 'Test Title', 'D', 'low', '2020-01-01T00:00:00+00:00');

        $this->assertSame('2020-01-01T00:00:00+00:00', (string) $command->getDueDate());
    }

    public function test_construct_with_invalid_date_throws(): void
    {
        $this->assertFieldError('dueDate', InvalidValueException::INVALID_FORMAT, static fn () => new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'low', '01-01-2030 00:00:00'));
    }

    public function test_construct_without_due_date(): void
    {
        $this->assertNull(new CreateTaskCommand(self::ID, 'Test Title', 'Test Description', 'high', null)->getDueDate());
    }
}
