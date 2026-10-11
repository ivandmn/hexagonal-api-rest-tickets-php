<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Factory\TaskFactory;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class CreateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskFactory $taskFactory,
        private TaskRepositoryInterface $taskRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateTaskCommand $command): CreateTaskResponse
    {
        $task = $this->taskFactory->register(
            $command->getTitle(),
            $command->getDescription(),
            $command->getPriority(),
            $command->getDueDate(),
        );

        $this->taskRepository->create($task);

        $this->logger->info('Task Created', ['task_id' => $task->getId()->value(), 'task_title' => $task->getTitle()->value()]);

        return new CreateTaskResponse($task);
    }
}
