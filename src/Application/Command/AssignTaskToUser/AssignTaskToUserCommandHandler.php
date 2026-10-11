<?php

declare(strict_types=1);

namespace App\Application\Command\AssignTaskToUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\TaskView;
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

    public function __invoke(AssignTaskToUserCommand $command): TaskView
    {
        $task = $this->taskRepository->findById($command->getTaskId()) ?? throw new TaskNotFoundException($command->getTaskId());
        $user = $this->userRepository->findById($command->getUserId()) ?? throw new UserNotFoundException($command->getUserId());

        $task->assignTo($user);

        $context = ['task_id' => $task->getId()->value(), 'user_id' => $user->getId()->value()];

        if (!$task->isUpdated()) {
            $this->logger->info('Task already assigned to this user', $context);

            return TaskView::fromTask($task);
        }

        $this->taskRepository->update($task);

        $this->logger->info('Task assigned to user', $context);

        return TaskView::fromTask($task);
    }
}
