<?php

declare(strict_types=1);

namespace App\Application\Command\AssignTaskToUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class AssignTaskToUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AssignTaskToUserCommand $command): AssignTaskToUserResponse
    {
        $taskId = $command->getTaskId();
        $userId = $command->getUserId();

        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException($taskId);
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new UserNotFoundException($userId);
        }

        $task->assignTo($user);

        if (!$task->isUpdated()) {
            $this->logger->info('Task not Assigned to User (no changes detected)', [
                'task_id'    => $task->getId()->value(),
                'task_title' => $task->getTitle()->value(),
                'user_id'    => $task->getAssignedUser()?->getId()->value(),
                'user_email' => $user->getEmail()->value(),
            ]);

            return new AssignTaskToUserResponse($task, $user);
        }

        $this->taskRepository->update($task);

        $this->logger->info(
            'Task Assigned to User',
            [
                'task_id'    => $task->getId()->value(),
                'task_title' => $task->getTitle()->value(),
                'user_id'    => $task->getAssignedUser()?->getId()->value(),
                'user_email' => $user->getEmail()->value(),
            ],
        );

        return new AssignTaskToUserResponse($task, $user);
    }
}
