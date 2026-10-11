<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\DeleteTask;

use App\Application\Command\DeleteTask\DeleteTaskResponse;
use App\Domain\Model\Task;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use PHPUnit\Framework\TestCase;

final class DeleteTaskResponseTest extends TestCase
{
    public function test_json_serialize_returns_expected_payload(): void
    {
        $task = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-0000000000aa'),
            TaskTitle::fromString('Sample Task'),
            TaskDescription::fromString('Desc'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            TaskDueDate::fromDate(new \DateTime('2030-01-01T00:00:00+00:00')),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
            new \DateTime('2030-01-02T00:00:00+00:00')
        );

        $response = new DeleteTaskResponse($task);
        $this->assertInstanceOf(\JsonSerializable::class, $response);

        $data = $response->jsonSerialize();
        $this->assertSame('Task "Sample Task" has been successfully deleted', $data['message']);
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-0000000000aa', $data['task_id']);
    }

    public function test_to_array_matches_json_serialize(): void
    {
        $task = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-0000000000bb'),
            TaskTitle::fromString('Another Task'),
            TaskDescription::fromString('Another'),
            TaskStatus::PENDING,
            TaskPriority::LOW
        );

        $response = new DeleteTaskResponse($task);
        $this->assertSame($response->jsonSerialize(), $response->toArray());
    }
}
