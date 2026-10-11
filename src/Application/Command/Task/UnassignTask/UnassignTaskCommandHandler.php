<?php

declare(strict_types=1);

namespace App\Application\Command\Task\UnassignTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\Task\TaskView;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Unassigning a task nobody has is a no-op, not an error: the result is the same.
 */
final readonly class UnassignTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UnassignTaskCommand $command): TaskView
    {
        $task = $this->taskRepository->findById($command->getTaskId()) ?? throw new TaskNotFoundException($command->getTaskId());

        $previousUser = $task->getAssignedUser();
        $task->unassign($this->clock->now());

        if (!$task->isUpdated()) {
            $this->logger->info('Task already unassigned', ['task_id' => $task->getId()->value()]);

            return TaskView::fromTask($task);
        }

        $this->taskRepository->update($task);

        $this->logger->info('Task unassigned', ['task_id' => $task->getId()->value(), 'previous_user_id' => $previousUser?->getId()->value()]);

        return TaskView::fromTask($task);
    }
}
