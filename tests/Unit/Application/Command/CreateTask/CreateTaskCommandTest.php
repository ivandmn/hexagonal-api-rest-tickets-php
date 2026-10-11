<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateTask;

use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\FutureDate;
use PHPUnit\Framework\TestCase;

final class CreateTaskCommandTest extends TestCase
{
    use AssertsDomainError;

    public function test_builds_the_value_objects(): void
    {
        $dueDate = FutureDate::on()->format(TaskDueDate::FORMAT);
        $command = new CreateTaskCommand('Test Title', 'Test Description', 'medium', $dueDate);

        $this->assertSame('Test Title', $command->getTitle()->value());
        $this->assertSame('Test Description', $command->getDescription()->value());
        $this->assertSame(TaskPriority::MEDIUM, $command->getPriority());
        $this->assertSame($dueDate, (string) $command->getDueDate());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertDomainError(InvalidValueException::TOO_SHORT, static fn () => new CreateTaskCommand('abc', 'D', 'low', null));
        $this->assertDomainError(InvalidValueException::INVALID_CHOICE, static fn () => new CreateTaskCommand('Test Title', 'D', 'urgent', null));
        $this->assertDomainError(TaskDueDateInPastException::ERROR_CODE, static fn () => new CreateTaskCommand('Test Title', 'D', 'low', '2020-01-01T00:00:00+00:00'));
    }

    public function test_construct_with_invalid_date_throws(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'low',
            '01-01-2030 00:00:00',
        ));
    }

    public function test_construct_without_due_date(): void
    {
        $command = new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'high',
            null,
        );

        $this->assertNull($command->getDueDate());
    }
}
