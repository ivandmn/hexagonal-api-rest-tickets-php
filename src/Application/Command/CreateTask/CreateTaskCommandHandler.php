<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\TaskView;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class CreateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateTaskCommand $command): TaskView
    {
        $task = Task::create(
            $command->getTaskId(),
            $command->getTitle(),
            $command->getDescription(),
            $this->clock->now(),
            $command->getPriority(),
            $command->getDueDate(),
        );

        $this->taskRepository->create($task);

        $this->logger->info('Task created', ['task_id' => $task->getId()->value(), 'task_title' => $task->getTitle()->value()]);

        return TaskView::fromTask($task);
    }
}
