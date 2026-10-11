<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\UpdateTask;

use App\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Application\Exception\InvalidDateFormat;
use App\Domain\ValueObject\Task\TaskDueDate;
use PHPUnit\Framework\TestCase;

final class UpdateTaskCommandTest extends TestCase
{
    public function test_getters_return_values_and_parsed_due_date(): void
    {
        $req = new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000001',
            'Valid Title',
            'Some description',
            'in_progress',
            'high',
            '2030-01-01T00:00:00+00:00'
        );

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', $req->getTaskId());
        $this->assertSame('Valid Title', $req->getTitle());
        $this->assertSame('Some description', $req->getDescription());
        $this->assertSame('in_progress', $req->getStatus());
        $this->assertSame('high', $req->getPriority());
        $this->assertInstanceOf(\DateTime::class, $req->getDueDate());
        $this->assertSame('2030-01-01T00:00:00+00:00', $req->getDueDate()->format(TaskDueDate::FORMAT));
    }

    public function test_nullables_are_accepted_and_due_date_can_be_null(): void
    {
        $req = new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000002',
            null,
            null,
            null,
            null,
            null
        );

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000002', $req->getTaskId());
        $this->assertNull($req->getTitle());
        $this->assertNull($req->getDescription());
        $this->assertNull($req->getStatus());
        $this->assertNull($req->getPriority());
        $this->assertNull($req->getDueDate());
    }

    public function test_throws_on_invalid_due_date_format(): void
    {
        $this->expectException(InvalidDateFormat::class);

        new UpdateTaskCommand(
            '018f9f9a-aaaa-bbbb-cccc-000000000003',
            'Another Title',
            null,
            null,
            null,
            '01-01-2030 00:00:00'
        );
    }
}
