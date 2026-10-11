<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTaskRepositoryTest extends KernelTestCase
{
    public function test_counts_the_tasks_of_a_user_by_completion(): void
    {
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        $tasks = static::getContainer()->get(TaskRepositoryInterface::class);
        $this->assertInstanceOf(UserRepositoryInterface::class, $users);
        $this->assertInstanceOf(TaskRepositoryInterface::class, $tasks);

        $jane = new User(UserId::new(), UserEmail::fromString('jane@example.com'), UserName::fromString('Jane Doe'));
        $john = new User(UserId::new(), UserEmail::fromString('john@example.com'), UserName::fromString('John Doe'));
        $users->save($jane);
        $users->save($john);

        foreach ([TaskStatus::PENDING, TaskStatus::IN_PROGRESS, TaskStatus::COMPLETED, TaskStatus::COMPLETED] as $status) {
            $tasks->create($this->task($status, $jane));
        }
        $tasks->create($this->task(TaskStatus::PENDING, $john));
        $tasks->create($this->task(TaskStatus::PENDING, null));

        $this->assertSame(4, $tasks->countByUser($jane->getId()));
        $this->assertSame(2, $tasks->countUnfinishedByUser($jane->getId()));
        $this->assertSame(1, $tasks->countUnfinishedByUser($john->getId()));
        $this->assertSame(0, $tasks->countByUser(UserId::new()));
    }

    private function task(TaskStatus $status, ?User $assignee): Task
    {
        return new Task(TaskId::new(), TaskTitle::fromString('Some task'), TaskDescription::fromString('D'), $status, TaskPriority::LOW, $assignee);
    }
}
