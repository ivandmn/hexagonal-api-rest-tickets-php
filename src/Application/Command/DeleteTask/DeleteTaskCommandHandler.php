<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskId;

final readonly class DeleteTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(DeleteTaskCommand $request): DeleteTaskResponse
    {
        $taskId = TaskId::fromString($request->getTaskId());

        $task = $this->taskRepository->findById($taskId);

        if (!$task) {
            throw new TaskNotFoundException('Task with this ID does not exist');
        }

        if (!$task->canBeDeleted()) {
            throw new TaskDeletionNotAllowedException(\sprintf('Tasks with status "%s" cannot be deleted', $task->getStatus()->value));
        }

        $this->taskRepository->delete($task);

        $this->logger->info('Task deleted', ['task_id' => $taskId->value(), 'task_title' => $task->getTitle()]);

        return new DeleteTaskResponse($task);
    }
}
