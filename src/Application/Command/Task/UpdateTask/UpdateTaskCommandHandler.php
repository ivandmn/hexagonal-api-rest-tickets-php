<?php

declare(strict_types=1);

namespace App\Application\Command\Task\UpdateTask;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\Task\TaskView;
use App\Application\Event\EventBusInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Repository\TaskRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class UpdateTaskCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private EventBusInterface $eventBus,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UpdateTaskCommand $command): TaskView
    {
        $task = $this->taskRepository->findById($command->getTaskId()) ?? throw new TaskNotFoundException($command->getTaskId());
        $now  = $this->clock->now();

        if (null !== $title = $command->getTitle()) {
            $task->changeTitle($title, $now);
        }

        if (null !== $description = $command->getDescription()) {
            $task->changeDescription($description, $now);
        }

        if (null !== $status = $command->getStatus()) {
            $task->changeStatus($status, $now);
        }

        if (null !== $priority = $command->getPriority()) {
            $task->changePriority($priority, $now);
        }

        if ($command->shouldClearDueDate()) {
            $task->changeDueDate(null, $now);
        } elseif (null !== $dueDate = $command->getDueDate()) {
            $task->changeDueDate($dueDate, $now);
        }

        if (!$task->isUpdated()) {
            $this->logger->info('Task not updated (no changes detected)', ['task_id' => $task->getId()->value()]);

            return TaskView::fromTask($task);
        }

        $this->taskRepository->update($task);

        $this->logger->info('Task updated', ['task_id' => $task->getId()->value()]);

        $this->eventBus->dispatch(...$task->pullDomainEvents());

        return TaskView::fromTask($task);
    }
}
