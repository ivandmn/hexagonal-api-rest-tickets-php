<?php

declare(strict_types=1);

namespace App\Application\Command\User\DeleteUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Event\EventBusInterface;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;

/**
 * A user can only be deleted once all their tasks are completed (User::delete). Their (completed)
 * tasks are then deleted with them: the task → user foreign key cascades on delete.
 */
final readonly class DeleteUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TaskRepositoryInterface $taskRepository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeleteUserCommand $command): void
    {
        $userId = $command->getUserId();
        $user   = $this->userRepository->findById($userId) ?? throw new UserNotFoundException($userId);

        $unfinishedTasks = $this->taskRepository->countUnfinishedByUser($userId);
        $completedTasks  = $this->taskRepository->countByUser($userId) - $unfinishedTasks;

        $user->delete($unfinishedTasks, $completedTasks, $this->clock->now());

        $this->userRepository->delete($user);
        $this->eventBus->dispatch(...$user->pullDomainEvents());
    }
}
