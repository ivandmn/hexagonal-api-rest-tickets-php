<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskCriteria;
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

        $jane = User::create(UserId::new(), UserEmail::fromString('jane@example.com'), UserName::fromString('Jane Doe'), new \DateTimeImmutable());
        $john = User::create(UserId::new(), UserEmail::fromString('john@example.com'), UserName::fromString('John Doe'), new \DateTimeImmutable());
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

    public function test_finds_and_counts_by_typed_criteria(): void
    {
        $tasks = $this->tasks();

        $tasks->create($this->task(TaskStatus::PENDING, null, TaskPriority::HIGH));
        $tasks->create($this->task(TaskStatus::PENDING, null, TaskPriority::LOW));
        $tasks->create($this->task(TaskStatus::IN_PROGRESS, null, TaskPriority::HIGH));

        $cases = [
            'no filters'    => [new TaskCriteria(), 3],
            'status'        => [new TaskCriteria(status: TaskStatus::PENDING), 2],
            'priority'      => [new TaskCriteria(priority: TaskPriority::HIGH), 2],
            'both'          => [new TaskCriteria(TaskStatus::PENDING, TaskPriority::HIGH), 1],
            'nothing found' => [new TaskCriteria(status: TaskStatus::COMPLETED), 0],
        ];

        foreach ($cases as $case => [$criteria, $expected]) {
            $found = $tasks->findByCriteria($criteria, 1, 10);

            $this->assertCount($expected, $found, $case);
            $this->assertSame($expected, $tasks->countByCriteria($criteria), $case);
            foreach ($found as $task) {
                $this->assertTrue(null === $criteria->status || $criteria->status === $task->getStatus(), $case);
                $this->assertTrue(null === $criteria->priority || $criteria->priority === $task->getPriority(), $case);
            }
        }
    }

    public function test_pages_are_newest_first_and_never_overlap(): void
    {
        $tasks = $this->tasks();
        $ids   = [];

        // Same creation instant for all of them: the order must still be stable across pages.
        foreach (\range(1, 5) as $_) {
            $task  = $this->task(TaskStatus::PENDING, null, createdAt: new \DateTimeImmutable('2026-01-01T10:00:00+00:00'));
            $ids[] = $task->getId()->value();
            $tasks->create($task);
        }
        $newest = $this->task(TaskStatus::PENDING, null, createdAt: new \DateTimeImmutable('2026-01-02T10:00:00+00:00'));
        $tasks->create($newest);

        $pages = \array_map(
            static fn (int $page): array => \array_map(static fn (Task $task): string => $task->getId()->value(), $tasks->findByCriteria(new TaskCriteria(), $page, 2)),
            [1, 2, 3, 4],
        );

        $this->assertSame($newest->getId()->value(), $pages[0][0]);
        $this->assertSame([2, 2, 2, 0], \array_map(\count(...), $pages));
        $this->assertEqualsCanonicalizing([...$ids, $newest->getId()->value()], \array_merge(...$pages));
    }

    private function tasks(): TaskRepositoryInterface
    {
        $tasks = static::getContainer()->get(TaskRepositoryInterface::class);
        $this->assertInstanceOf(TaskRepositoryInterface::class, $tasks);

        return $tasks;
    }

    private function task(TaskStatus $status, ?User $assignee, TaskPriority $priority = TaskPriority::LOW, \DateTimeImmutable $createdAt = new \DateTimeImmutable()): Task
    {
        return new Task(TaskId::new(), TaskTitle::fromString('Some task'), TaskDescription::fromString('D'), $status, $priority, $assignee, null, $createdAt, null);
    }
}
