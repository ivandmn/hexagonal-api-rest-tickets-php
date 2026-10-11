<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
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

    public function findAll(): array
    {
        return $this->entityManager->getRepository(Task::class)->findAll();
    }

    public function findById(TaskId $id): ?Task
    {
        return $this->entityManager->find(Task::class, $id);
    }

    public function findByUserId(UserId $userId): array
    {
        return $this->entityManager->getRepository(Task::class)->findBy(['assignedUser' => $userId]);
    }

    public function findByFilters(array $filters, int $page, int $maxItems): array
    {
        return $this->entityManager->getRepository(Task::class)->findBy(
            $filters,
            ['createdAt' => 'DESC'],
            $maxItems,
            ($page - 1) * $maxItems
        );
    }
}
