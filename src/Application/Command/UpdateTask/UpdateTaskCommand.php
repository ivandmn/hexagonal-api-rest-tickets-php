<?php

declare(strict_types=1);

namespace App\Application\Command\UpdateTask;

use App\Application\Command\CommandInterface;
use App\Domain\ValueObject\Task\TaskDueDate;

final class UpdateTaskCommand implements CommandInterface
{
    private ?\DateTimeImmutable $dueDate;

    public function __construct(
        private string $taskId,
        private ?string $title,
        private ?string $description,
        private ?string $stauts,
        private ?string $priority,
        ?string $dueDate,
        // null fields mean "leave unchanged", so removing the due date needs an explicit flag
        private bool $clearDueDate = false,
    ) {
        $this->dueDate = null !== $dueDate ? TaskDueDate::parse($dueDate) : null;
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStatus(): ?string
    {
        return $this->stauts;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function shouldClearDueDate(): bool
    {
        return $this->clearDueDate;
    }
}
