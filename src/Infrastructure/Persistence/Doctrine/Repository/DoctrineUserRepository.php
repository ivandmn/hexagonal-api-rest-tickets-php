<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function delete(User $user): void
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function findAll(): array
    {
        return $this->entityManager->getRepository(User::class)->findAll();
    }

    public function findById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function findByEmail(UserEmail $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
