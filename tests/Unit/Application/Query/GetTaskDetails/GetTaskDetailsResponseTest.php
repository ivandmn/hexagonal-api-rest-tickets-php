<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\GetTaskDetails;

use App\Application\Query\GetTaskDetails\GetTaskDetailsResponse;
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
use App\Tests\Support\FutureDate;
use PHPUnit\Framework\TestCase;

final class GetTaskDetailsResponseTest extends TestCase
{
    public function test_json_serialize_with_all_fields(): void
    {
        $taskId      = TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000111');
        $title       = TaskTitle::fromString('Test Title');
        $description = TaskDescription::fromString('Test Description');
        $status      = TaskStatus::IN_PROGRESS;
        $priority    = TaskPriority::HIGH;
        $assignee    = new User(
            UserId::fromString('018f9f9a-dddd-eeee-ffff-000000000222'),
            UserEmail::fromString('user@example.com'),
            UserName::fromString('User Name'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );
        $due     = TaskDueDate::fromDate(FutureDate::on(day: 2, time: '10:20:30'));
        $created = new \DateTimeImmutable('2030-01-01T10:00:00+00:00');
        $updated = new \DateTimeImmutable('2030-01-03T12:34:56+00:00');

        $task = new Task(
            $taskId,
            $title,
            $description,
            $status,
            $priority,
            $assignee,
            $due,
            $created,
            $updated,
        );

        $response = new GetTaskDetailsResponse($task);

        $this->assertInstanceOf(\JsonSerializable::class, $response);

        $data = $response->jsonSerialize();

        $this->assertSame($taskId->value(), $data['id']);
        $this->assertSame('Test Title', $data['title']);
        $this->assertSame('Test Description', $data['description']);
        $this->assertSame('in_progress', $data['status']);
        $this->assertSame('high', $data['priority']);
        $this->assertSame('018f9f9a-dddd-eeee-ffff-000000000222', $data['assignedTo']['id']);
        $this->assertSame('User Name', $data['assignedTo']['name']);
        $this->assertSame(FutureDate::on(day: 2, time: '10:20:30')->format(\DATE_ATOM), $data['dueDate']);
        $this->assertSame('2030-01-01T10:00:00+00:00', $data['createdAt']);
        $this->assertSame('2030-01-03T12:34:56+00:00', $data['updatedAt']);
    }

    public function test_json_serialize_with_nullables_and_to_array_matches(): void
    {
        $task = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000333'),
            TaskTitle::fromString('Another Title'),
            TaskDescription::fromString('Another Description'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2031-01-01T00:00:00+00:00'),
            null,
        );

        $response = new GetTaskDetailsResponse($task);
        $data     = $response->jsonSerialize();

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000333', $data['id']);
        $this->assertSame('Another Title', $data['title']);
        $this->assertSame('Another Description', $data['description']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('low', $data['priority']);
        $this->assertNull($data['assignedTo']);
        $this->assertNull($data['dueDate']);
        $this->assertSame('2031-01-01T00:00:00+00:00', $data['createdAt']);
        $this->assertNull($data['updatedAt']);

        $this->assertSame($data, $response->toArray());
    }
}
