<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Event;

use App\Application\Event\Task\LogTaskAssignedEventHandler;
use App\Application\Event\Task\LogTaskCompletedEventHandler;
use App\Application\Event\User\LogUserDeletedEventHandler;
use App\Domain\Event\Task\TaskAssigned;
use App\Domain\Event\Task\TaskCompleted;
use App\Domain\Event\User\UserDeleted;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

final class LogEventHandlersTest extends TestCase
{
    private const string TASK_ID = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';
    private const string USER_ID = 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e';
    private const string PREV_ID = '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63';
    private const string AT      = '2030-01-01T10:00:00+00:00';

    private SpyLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new SpyLogger();
    }

    public function test_logs_task_assigned(): void
    {
        new LogTaskAssignedEventHandler($this->logger)(new TaskAssigned(TaskId::fromString(self::TASK_ID), UserId::fromString(self::USER_ID), UserId::fromString(self::PREV_ID), DateTime::fromString(self::AT)));

        $this->assertSame([[
            'level'   => 'info',
            'message' => 'Task assigned to user',
            'context' => ['event' => 'task.assigned', 'occurred_on' => self::AT, 'task_id' => self::TASK_ID, 'user_id' => self::USER_ID, 'previous_user_id' => self::PREV_ID],
        ]], $this->logger->records);
    }

    public function test_logs_task_completed(): void
    {
        new LogTaskCompletedEventHandler($this->logger)(new TaskCompleted(TaskId::fromString(self::TASK_ID), null, DateTime::fromString(self::AT)));

        $this->assertSame([[
            'level'   => 'info',
            'message' => 'Task completed',
            'context' => ['event' => 'task.completed', 'occurred_on' => self::AT, 'task_id' => self::TASK_ID, 'assigned_user_id' => null],
        ]], $this->logger->records);
    }

    public function test_logs_user_deleted(): void
    {
        new LogUserDeletedEventHandler($this->logger)(new UserDeleted(UserId::fromString(self::USER_ID), 2, DateTime::fromString(self::AT)));

        $this->assertSame([[
            'level'   => 'info',
            'message' => 'User deleted',
            'context' => ['event' => 'user.deleted', 'occurred_on' => self::AT, 'user_id' => self::USER_ID, 'deleted_tasks' => 2],
        ]], $this->logger->records);
    }
}
