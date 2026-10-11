<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDueDate;

final readonly class UpdateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateTaskCommand $command): UpdateTaskResponse
    {
        $taskId = $command->getTaskId();
        $task   = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException($taskId);
        }

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

        $dueDate = $command->getDueDate();
        if ($command->shouldClearDueDate()) {
            $task->changeDueDate(null);
        } elseif (null !== $dueDate && !$this->isCurrentDueDate($task, $dueDate)) {
            // Re-sending the due date the task already has is a no-op, even if it is in the past by now;
            // a new one must be in the future (checked by TaskDueDate).
            $task->changeDueDate(TaskDueDate::fromDate($dueDate));
        }

        if (!$task->isUpdated()) {
            $this->logger->info('Task not updated (no changes detected)', [
                'task_id' => $task->getId()->value(),
            ]);

            return new UpdateTaskResponse($task);
        }

        $this->taskRepository->update($task);

        $this->logger->info('Task Updated', ['task_id' => $task->getId()->value()]);

        return new UpdateTaskResponse($task);
    }

    private function isCurrentDueDate(Task $task, \DateTimeImmutable $dueDate): bool
    {
        return $task->getDueDate()?->value()->getTimestamp() === $dueDate->getTimestamp();
    }
}
