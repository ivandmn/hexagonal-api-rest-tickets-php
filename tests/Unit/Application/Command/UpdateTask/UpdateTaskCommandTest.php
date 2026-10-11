<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command\UpdateTask;

use App\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class UpdateTaskCommandTest extends TestCase
{
    use AssertsDomainError;

    public function test_getters_return_values_and_parsed_due_date(): void
    {
        $req = new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000001',
            'Valid Title',
            'Some description',
            'in_progress',
            'high',
            '2030-01-01T00:00:00+00:00',
        );

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', $req->getTaskId()->value());
        $this->assertSame('Valid Title', $req->getTitle()?->value());
        $this->assertSame('Some description', $req->getDescription()?->value());
        $this->assertSame(TaskStatus::IN_PROGRESS, $req->getStatus());
        $this->assertSame(TaskPriority::HIGH, $req->getPriority());
        $this->assertSame('2030-01-01T00:00:00+00:00', (string) $req->getDueDate());
    }

    public function test_nullables_are_accepted_and_due_date_can_be_null(): void
    {
        $req = new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000002',
            null,
            null,
            null,
            null,
            null,
        );

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000002', $req->getTaskId()->value());
        $this->assertNull($req->getTitle());
        $this->assertNull($req->getDescription());
        $this->assertNull($req->getStatus());
        $this->assertNull($req->getPriority());
        $this->assertNull($req->getDueDate());
    }

    public function test_a_past_due_date_is_only_checked_for_format(): void
    {
        // "Not in the past" needs the clock and the task's current due date: checked by the handler.
        $command = new UpdateTaskCommand('018f9f9a-aaaa-bbbb-cccc-000000000004', null, null, null, null, '2020-01-01T00:00:00+00:00');

        $this->assertSame('2020-01-01T00:00:00+00:00', (string) $command->getDueDate());
    }

    public function test_rejects_invalid_values_when_built(): void
    {
        $this->assertFieldError('taskId', InvalidValueException::INVALID_FORMAT, static fn () => new UpdateTaskCommand('not-a-uuid', null, null, null, null, null));
        $this->assertFieldError('status', InvalidValueException::INVALID_CHOICE, static fn () => new UpdateTaskCommand('018f9f9a-aaaa-bbbb-cccc-000000000005', null, null, 'done', null, null));
    }

    public function test_throws_on_invalid_due_date_format(): void
    {
        $this->assertFieldError('dueDate', InvalidValueException::INVALID_FORMAT, static fn () => new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000003',
            'Another Title',
            null,
            null,
            null,
            '01-01-2030 00:00:00',
        ));
    }
}
