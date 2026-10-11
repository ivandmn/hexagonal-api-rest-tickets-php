<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;

final readonly class UpdateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(UpdateTaskCommand $request): UpdateTaskResponse
    {
        $taskId = TaskId::fromString($request->getTaskId());
        $task   = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException('Task with this ID does not exist');
        }

        if (null !== $request->getTitle()) {
            $task->changeTitle(TaskTitle::fromString($request->getTitle()));
        }

        if (null !== $request->getDescription()) {
            $task->changeDescription(TaskDescription::fromString($request->getDescription()));
        }

        if (null !== $request->getStatus()) {
            $task->changeStatus(TaskStatus::fromString($request->getStatus()));
        }

        if (null !== $request->getPriority()) {
            $task->changePriority(TaskPriority::fromString($request->getPriority()));
        }

        if (null !== $request->getDueDate()) {
            $task->changeDueDate(TaskDueDate::fromDate($request->getDueDate()));
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
}
