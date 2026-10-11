<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateTask;

use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Application\Exception\InvalidDateFormat;
use App\Domain\ValueObject\Task\TaskDueDate;
use PHPUnit\Framework\TestCase;

final class CreateTaskCommandTest extends TestCase
{
    public function test_construct_with_valid_rfc3339_sets_properties(): void
    {
        $req = new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'medium',
            '2030-01-01T00:00:00+00:00'
        );

        $this->assertSame('Test Title', $req->getTitle());
        $this->assertSame('Test Description', $req->getDescription());
        $this->assertSame('medium', $req->getPriority());
        $this->assertInstanceOf(\DateTime::class, $req->getDueDate());
        $this->assertSame(
            '2030-01-01T00:00:00+00:00',
            $req->getDueDate()->format(TaskDueDate::FORMAT)
        );
    }

    public function test_construct_with_invalid_date_throws(): void
    {
        $this->expectException(InvalidDateFormat::class);
        new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'low',
            '01-01-2030 00:00:00'
        );
    }

    public function test_construct_with_null_due_date_currently_errors_due_to_non_nullable_property(): void
    {
        $this->expectException(\TypeError::class);
        new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'high',
            null
        );
    }
}
