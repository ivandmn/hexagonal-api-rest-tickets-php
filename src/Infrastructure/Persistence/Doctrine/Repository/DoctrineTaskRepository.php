<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Model\Task;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskPriorityType;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskStatusType;
use App\Infrastructure\Persistence\Doctrine\Type\User\UserIdType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(Task $task): void
    {
        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function update(Task $task): void
    {
        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    public function delete(Task $task): void
    {
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }

    public function findById(TaskId $id): ?Task
    {
        return $this->entityManager->find(Task::class, $id);
    }

    public function findByCriteria(TaskCriteria $criteria, int $page, int $maxItems): array
    {
        /** @var list<Task> */
        return $this->matching($criteria)
            ->select('t')
            // The id breaks ties between tasks created in the same second, so pages never overlap.
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'ASC')
            ->setFirstResult(($page - 1) * $maxItems)
            ->setMaxResults($maxItems)
            ->getQuery()
            ->getResult()
        ;
    }

    public function countByCriteria(TaskCriteria $criteria): int
    {
        return (int) $this->matching($criteria)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
    }

    public function countByUser(UserId $userId): int
    {
        return $this->countAssignedTo($userId, onlyUnfinished: false);
    }

    public function countUnfinishedByUser(UserId $userId): int
    {
        return $this->countAssignedTo($userId, onlyUnfinished: true);
    }

    private function countAssignedTo(UserId $userId, bool $onlyUnfinished): int
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('t.assignedUser = :user')
            ->setParameter('user', $userId, UserIdType::NAME)
        ;

        if ($onlyUnfinished) {
            $query->andWhere('t.status <> :completed')->setParameter('completed', TaskStatus::COMPLETED, TaskStatusType::NAME);
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Parameters carry their DBAL type: findBy() would turn the enums into plain strings first.
     */
    private function matching(TaskCriteria $criteria): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->from(Task::class, 't');

        if (null !== $criteria->status) {
            $query->andWhere('t.status = :status')->setParameter('status', $criteria->status, TaskStatusType::NAME);
        }

        if (null !== $criteria->priority) {
            $query->andWhere('t.priority = :priority')->setParameter('priority', $criteria->priority, TaskPriorityType::NAME);
        }

        return $query;
    }
}
