<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\AssignTaskToUser;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserResponse;
use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;

final class AssignTaskToUserResponseTest extends TestCase
{
    public function test_json_serialize_returns_expected_payload(): void
    {
        $taskId = TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000111');
        $userId = UserId::fromString('018f9f9a-dddd-eeee-ffff-000000000222');

        $task = new Task(
            $taskId,
            TaskTitle::fromString('Test Title'),
            TaskDescription::fromString('Test Description'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            null,
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $user = new User(
            $userId,
            UserEmail::fromString('assignee@example.com'),
            UserName::fromString('Assignee User'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );

        $response = new AssignTaskToUserResponse($task, $user);

        $this->assertInstanceOf(\JsonSerializable::class, $response);

        $data = $response->jsonSerialize();

        $this->assertSame(
            'Task "Test Title" has been assigned to "assignee@example.com"',
            $data['message'],
        );
        $this->assertSame($taskId->value(), $data['task_id']);
        $this->assertSame($userId->value(), $data['user_id']);
    }

    public function test_to_array_matches_json_serialize(): void
    {
        $task = new Task(
            TaskId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000333'),
            TaskTitle::fromString('Another Title'),
            TaskDescription::fromString('Another Description'),
        );

        $user = new User(
            UserId::fromString('018f9f9a-dddd-eeee-ffff-000000000444'),
            UserEmail::fromString('user@example.com'),
            UserName::fromString('User Name'),
        );

        $response = new AssignTaskToUserResponse($task, $user);

        $this->assertSame($response->jsonSerialize(), $response->toArray());
    }
}
