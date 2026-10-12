<?php

declare(strict_types=1);

namespace App\Application\Command\Task\AssignTaskToUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\Task\TaskView;
use App\Application\Event\EventBusInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class AssignTaskToUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private UserRepositoryInterface $userRepository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
        private LoggerInterface $appLogger,
    ) {
    }

    public function __invoke(AssignTaskToUserCommand $command): TaskView
    {
        $task = $this->taskRepository->findById($command->getTaskId()) ?? throw new TaskNotFoundException($command->getTaskId());
        $user = $this->userRepository->findById($command->getUserId()) ?? throw new UserNotFoundException($command->getUserId());

        $task->assignTo($user, $this->clock->now());

        if (!$task->isUpdated()) {
            $this->appLogger->info('Task already assigned to this user', ['task_id' => $task->getId()->value(), 'user_id' => $user->getId()->value()]);

            return TaskView::fromTask($task);
        }

        $this->taskRepository->update($task);
        $this->eventBus->dispatch(...$task->pullDomainEvents());

        return TaskView::fromTask($task);
    }
}
