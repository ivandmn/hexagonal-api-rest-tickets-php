<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\TaskView;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class UpdateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateTaskCommand $command): TaskView
    {
        $task = $this->taskRepository->findById($command->getTaskId()) ?? throw new TaskNotFoundException($command->getTaskId());

        if (null !== $title = $command->getTitle()) {
            $task->changeTitle($title);
        }

        if (null !== $description = $command->getDescription()) {
            $task->changeDescription($description);
        }

        if (null !== $status = $command->getStatus()) {
            $task->changeStatus($status);
        }

        if (null !== $priority = $command->getPriority()) {
            $task->changePriority($priority);
        }

        if ($command->shouldClearDueDate()) {
            $task->changeDueDate(null, $this->clock->now());
        } elseif (null !== $dueDate = $command->getDueDate()) {
            $task->changeDueDate($dueDate, $this->clock->now());
        }

        if (!$task->isUpdated()) {
            $this->logger->info('Task not updated (no changes detected)', ['task_id' => $task->getId()->value()]);

            return TaskView::fromTask($task);
        }

        $this->taskRepository->update($task);

        $this->logger->info('Task updated', ['task_id' => $task->getId()->value()]);

        return TaskView::fromTask($task);
    }
}
