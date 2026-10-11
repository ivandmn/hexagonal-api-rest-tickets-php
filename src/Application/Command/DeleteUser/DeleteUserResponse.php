<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteUser;

use App\Domain\Model\User;

final readonly class DeleteUserResponse implements \JsonSerializable
{
    public function __construct(
        private User $user,
        private int $deletedTasks,
    ) {
    }

    /**
     * @return array{message: string, user_id: string, deleted_tasks: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'message'       => \sprintf('User "%s" has been successfully deleted', $this->user->getEmail()->value()),
            'user_id'       => $this->user->getId()->value(),
            'deleted_tasks' => $this->deletedTasks,
        ];
    }

    /**
     * @return array{message: string, user_id: string, deleted_tasks: int}
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
