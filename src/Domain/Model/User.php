<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\User\UserDeleted;
use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;

/**
 * Whether an email is free is not something a single user can know: the caller checks it (and the
 * repository enforces it) before changing it.
 */
final class User extends AggregateRoot
{
    public function __construct(
        private readonly UserId $id,
        private UserEmail $email,
        private UserName $name,
        private readonly DateTime $createdAt,
        private ?DateTime $updatedAt,
    ) {
    }

    public static function create(UserId $id, UserEmail $email, UserName $name, \DateTimeImmutable $now): self
    {
        return new self($id, $email, $name, DateTime::fromDateTime($now), null);
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getEmail(): UserEmail
    {
        return $this->email;
    }

    public function getName(): UserName
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function changeName(UserName $newName, \DateTimeImmutable $now): void
    {
        if ($this->name->equals($newName)) {
            return;
        }

        $this->name      = $newName;
        $this->updatedAt = DateTime::fromDateTime($now);
    }

    public function changeEmail(UserEmail $newEmail, \DateTimeImmutable $now): void
    {
        if ($this->email->equals($newEmail)) {
            return;
        }

        $this->email     = $newEmail;
        $this->updatedAt = DateTime::fromDateTime($now);
    }

    /**
     * Checks the user can go and records UserDeleted; removing it is up to the repository, and
     * their completed tasks go with them.
     *
     * @param int $unfinishedTasks assigned tasks still pending or in progress
     * @param int $completedTasks  assigned tasks already completed, deleted along with the user
     *
     * @throws UserDeletionNotAllowedException while any of their tasks is not completed
     */
    public function delete(int $unfinishedTasks, int $completedTasks, \DateTimeImmutable $now): void
    {
        if ($unfinishedTasks > 0) {
            throw new UserDeletionNotAllowedException($this->id, $unfinishedTasks);
        }

        $this->record(new UserDeleted($this->id, $completedTasks, DateTime::fromDateTime($now)));
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
