<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;

interface UserRepositoryInterface
{
    /**
     * @throws UserAlreadyExistsException if the email is already registered
     */
    public function save(User $user): void;

    public function delete(User $user): void;

    public function findAll(): array;

    public function findById(UserId $id): ?User;

    public function findByEmail(UserEmail $email): ?User;
}
