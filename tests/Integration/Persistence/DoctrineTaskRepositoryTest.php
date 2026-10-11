<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineTaskRepositoryTest extends KernelTestCase
{
    /**
     * Every column through its DBAL type and back, read from the database rather than from the
     * identity map. The due date has already passed: a stored one must still load.
     */
    public function test_a_task_round_trips_with_every_field(): void
    {
        $assignee = User::create(UserId::new(), UserEmail::fromString('Jane@Example.com'), UserName::fromString('Jane Doe'), new \DateTimeImmutable('2025-12-31T09:00:00+00:00'));
        $this->users()->save($assignee);

        $task = new Task(
            TaskId::new(),
            TaskTitle::fromString('Fix login redirect'),
            TaskDescription::fromString('Users land on a 404'),
            TaskStatus::IN_PROGRESS,
            TaskPriority::HIGH,
            $assignee,
            TaskDueDate::fromString('2020-01-01T10:00:00+02:00'),
            DateTime::fromString('2026-01-01T10:00:00+00:00'),
            DateTime::fromString('2026-01-02T13:30:00+02:00'),
        );
        $this->tasks()->create($task);
        $this->entityManager()->clear();

        $loaded = $this->tasks()->findById($task->getId());

        $this->assertNotNull($loaded);
        $this->assertNotSame($task, $loaded);
        $this->assertTrue($task->getId()->equals($loaded->getId()));
        $this->assertSame('Fix login redirect', $loaded->getTitle()->value());
        $this->assertSame('Users land on a 404', $loaded->getDescription()->value());
        $this->assertSame(TaskStatus::IN_PROGRESS, $loaded->getStatus());
        $this->assertSame(TaskPriority::HIGH, $loaded->getPriority());
        $this->assertSame('2020-01-01T08:00:00+00:00', (string) $loaded->getDueDate());
        $this->assertSame('2026-01-01T10:00:00+00:00', (string) $loaded->getCreatedAt());
        $this->assertSame('2026-01-02T11:30:00+00:00', (string) $loaded->getUpdatedAt());
        $this->assertTrue($task->getCreatedAt()->equals($loaded->getCreatedAt()), 'equals() holds after a round trip');

        $loadedAssignee = $loaded->getAssignedUser();
        $this->assertNotNull($loadedAssignee);
        $this->assertTrue($assignee->equals($loadedAssignee));
        $this->assertSame('jane@example.com', $loadedAssignee->getEmail()->value());
        $this->assertSame('Jane Doe', $loadedAssignee->getName()->value());
    }

    public function test_update_and_delete_reach_the_database(): void
    {
        $task = $this->task(TaskStatus::PENDING, null);
        $this->tasks()->create($task);

        $now = new \DateTimeImmutable('2026-03-04T05:06:07.890+01:00');
        $task->changeTitle(TaskTitle::fromString('Renamed task'), $now);
        $task->changeStatus(TaskStatus::IN_PROGRESS, $now);
        $this->tasks()->update($task);
        $this->entityManager()->clear();

        $updated = $this->tasks()->findById($task->getId());
        $this->assertNotNull($updated);
        $this->assertSame('Renamed task', $updated->getTitle()->value());
        $this->assertSame(TaskStatus::IN_PROGRESS, $updated->getStatus());
        $this->assertSame('2026-03-04T04:06:07+00:00', (string) $updated->getUpdatedAt());

        $this->tasks()->delete($updated);
        $this->entityManager()->clear();

        $this->assertNull($this->tasks()->findById($task->getId()));
    }

    public function test_counts_the_tasks_of_a_user_by_completion(): void
    {
        $users = $this->users();
        $tasks = $this->tasks();

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

    public function test_finds_by_assignee_and_due_date_range(): void
    {
        $jane = User::create(UserId::new(), UserEmail::fromString('jane@example.com'), UserName::fromString('Jane Doe'), new \DateTimeImmutable());
        $john = User::create(UserId::new(), UserEmail::fromString('john@example.com'), UserName::fromString('John Doe'), new \DateTimeImmutable());
        $this->users()->save($jane);
        $this->users()->save($john);

        $tasks = $this->tasks();
        $early = $this->task(TaskStatus::PENDING, $jane, dueDate: TaskDueDate::fromString('2030-01-01T00:00:00+00:00'));
        $mid   = $this->task(TaskStatus::PENDING, $jane, dueDate: TaskDueDate::fromString('2030-01-15T12:00:00+00:00'));
        $late  = $this->task(TaskStatus::PENDING, $john, dueDate: TaskDueDate::fromString('2030-01-31T23:59:59+00:00'));
        $noDue = $this->task(TaskStatus::PENDING, $jane);
        foreach ([$early, $mid, $late, $noDue, $this->task(TaskStatus::PENDING, null)] as $task) {
            $tasks->create($task);
        }

        $cases = [
            'assignee'               => [new TaskCriteria(assignedUserId: $jane->getId()), [$early, $mid, $noDue], 3],
            'unknown assignee'       => [new TaskCriteria(assignedUserId: UserId::new()), [], 0],
            // Inclusive at both ends, and compared as instants whatever offset the filter came in.
            'range, ends included'   => [new TaskCriteria(dueFrom: DateTime::fromString('2030-01-01T01:00:00+01:00'), dueTo: DateTime::fromString('2030-01-15T12:00:00Z')), [$early, $mid], 2],
            'from only'              => [new TaskCriteria(dueFrom: DateTime::fromString('2030-01-15T12:00:01Z')), [$late], 1],
            'to only'                => [new TaskCriteria(dueTo: DateTime::fromString('2029-12-31T23:59:59Z')), [], 0],
            'assignee and range'     => [new TaskCriteria(assignedUserId: $jane->getId(), dueFrom: DateTime::fromString('2030-01-10T00:00:00Z')), [$mid], 1],
        ];

        foreach ($cases as $case => [$criteria, $expected, $count]) {
            $found = \array_map(static fn (Task $task): string => $task->getId()->value(), $tasks->findByCriteria($criteria, 1, 10));

            $this->assertEqualsCanonicalizing(\array_map(static fn (Task $task): string => $task->getId()->value(), $expected), $found, $case);
            $this->assertSame($count, $tasks->countByCriteria($criteria), $case);
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
        return $this->service(TaskRepositoryInterface::class);
    }

    private function users(): UserRepositoryInterface
    {
        return $this->service(UserRepositoryInterface::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = static::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }

    private function task(
        TaskStatus $status,
        ?User $assignee,
        TaskPriority $priority = TaskPriority::LOW,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        ?TaskDueDate $dueDate = null,
    ): Task {
        return new Task(TaskId::new(), TaskTitle::fromString('Some task'), TaskDescription::fromString('D'), $status, $priority, $assignee, $dueDate, DateTime::fromDateTime($createdAt), null);
    }
}
