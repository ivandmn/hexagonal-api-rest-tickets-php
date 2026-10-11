<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandInterface;
use App\Domain\ValueObject\Task\TaskDueDate;

final class CreateTaskCommand implements CommandInterface
{
    private ?\DateTimeImmutable $dueDate;

    public function __construct(
        private string $title,
        private string $description,
        private string $priority,
        ?string $dueDate,
    ) {
        $this->dueDate = null !== $dueDate ? TaskDueDate::parse($dueDate) : null;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }
}
