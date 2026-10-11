<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Factory\TaskFactory;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;

final readonly class CreateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskFactory $taskFactory,
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateTaskCommand $request): CreateTaskResponse
    {
        $title       = TaskTitle::fromString($request->getTitle());
        $description = TaskDescription::fromString($request->getDescription());
        $priority    = TaskPriority::fromString($request->getPriority());
        $dueDate     = $request->getDueDate() ? TaskDueDate::fromDate($request->getDueDate()) : null;

        $task = $this->taskFactory->register(
            $title,
            $description,
            $priority,
            $dueDate,
        );

        $this->taskRepository->create($task);

        $this->logger->info('Task Created', ['task_id' => $task->getId()->value(), 'task_title' => $task->getTitle()->value()]);

        return new CreateTaskResponse($task);
    }
}
