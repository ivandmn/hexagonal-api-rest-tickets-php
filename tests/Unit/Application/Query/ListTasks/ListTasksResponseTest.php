<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\ListTasks;

use App\Application\Query\ListTasks\ListTasksResponse;
use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;

final class ListTasksResponseTest extends TestCase
{
    public function test_json_serialize_with_multiple_tasks_and_meta(): void
    {
        $assignee = new User(
            UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000123'),
            UserEmail::fromString('user@example.com'),
            UserName::fromString('User Name'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $t1 = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000111'),
            TaskTitle::fromString('Task One'),
            TaskDescription::fromString('D1'),
            TaskStatus::IN_PROGRESS,
            TaskPriority::HIGH,
            $assignee,
            TaskDueDate::fromDate(new \DateTimeImmutable('2030-01-02T03:04:05+00:00')),
            new \DateTimeImmutable('2030-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2030-01-03T12:34:56+00:00'),
        );

        $t2 = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000222'),
            TaskTitle::fromString('Task Two'),
            TaskDescription::fromString('D2'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2031-01-01T10:00:00+00:00'),
            null,
        );

        $resp = new ListTasksResponse([$t1, $t2], 2, 10);

        $this->assertInstanceOf(\JsonSerializable::class, $resp);
        $data = $resp->jsonSerialize();

        $this->assertCount(2, $data['data']);
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000111', $data['data'][0]['id']);
        $this->assertSame('Task One', $data['data'][0]['title']);
        $this->assertSame('D1', $data['data'][0]['description']);
        $this->assertSame('in_progress', $data['data'][0]['status']);
        $this->assertSame('high', $data['data'][0]['priority']);
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000123', $data['data'][0]['assignedTo']['id']);
        $this->assertSame('User Name', $data['data'][0]['assignedTo']['name']);
        $this->assertSame('2030-01-02T03:04:05+00:00', $data['data'][0]['dueDate']);
        $this->assertSame('2030-01-01T10:00:00+00:00', $data['data'][0]['createdAt']);
        $this->assertSame('2030-01-03T12:34:56+00:00', $data['data'][0]['updatedAt']);

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000222', $data['data'][1]['id']);
        $this->assertSame('Task Two', $data['data'][1]['title']);
        $this->assertNull($data['data'][1]['assignedTo']);
        $this->assertNull($data['data'][1]['dueDate']);
        $this->assertSame('2031-01-01T10:00:00+00:00', $data['data'][1]['createdAt']);
        $this->assertNull($data['data'][1]['updatedAt']);

        $this->assertSame(2, $data['meta']['total']);
        $this->assertSame(2, $data['meta']['page']);
        $this->assertSame(10, $data['meta']['limit']);
        $this->assertSame($data, $resp->toArray());
        $this->assertFalse($resp->isEmpty());

        $collected = [];
        foreach ($resp as $task) {
            $collected[] = $task;
        }
        $this->assertSame([$t1, $t2], $collected);
        $this->assertSame(2, $resp->count());
    }

    public function test_json_serialize_with_empty_list_uses_defaults_in_meta(): void
    {
        $resp = new ListTasksResponse([]);

        $data = $resp->jsonSerialize();
        $this->assertSame(0, $data['meta']['total']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(0, $data['meta']['limit']);
        $this->assertTrue($resp->isEmpty());
    }
}
