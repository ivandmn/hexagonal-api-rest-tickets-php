<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class DeleteTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteTaskCommand $command): void
    {
        $taskId = $command->getTaskId();
        $task   = $this->taskRepository->findById($taskId) ?? throw new TaskNotFoundException($taskId);

        if (!$task->canBeDeleted()) {
            throw new TaskDeletionNotAllowedException($taskId, $task->getStatus());
        }

        $this->taskRepository->delete($task);

        $this->logger->info('Task deleted', ['task_id' => $taskId->value(), 'task_title' => $task->getTitle()->value()]);
    }
}
