<?php

declare(strict_types=1);

namespace App\Application\Command\User\DeleteUser;

use App\Application\Command\CommandHandlerInterface;
use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * A user can only be deleted once all their tasks are completed. Their (completed) tasks are
 * then deleted with them: the task → user foreign key cascades on delete.
 */
final readonly class DeleteUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $userId = $command->getUserId();
        $user   = $this->userRepository->findById($userId) ?? throw new UserNotFoundException($userId);

        $unfinishedTasks = $this->taskRepository->countUnfinishedByUser($userId);
        if ($unfinishedTasks > 0) {
            throw new UserDeletionNotAllowedException($userId, $unfinishedTasks);
        }

        $deletedTasks = $this->taskRepository->countByUser($userId);
        $this->userRepository->delete($user);

        $this->logger->info('User deleted', ['user_id' => $userId->value(), 'deleted_tasks' => $deletedTasks]);
    }
}
