<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;

final readonly class CreateTaskCommand implements CommandInterface
{
    private TaskTitle $title;
    private TaskDescription $description;
    private TaskPriority $priority;
    private ?TaskDueDate $dueDate;

    public function __construct(string $title, string $description, string $priority, ?string $dueDate)
    {
        $this->title       = TaskTitle::fromString($title);
        $this->description = TaskDescription::fromString($description);
        $this->priority    = TaskPriority::fromString($priority);
        $this->dueDate     = null !== $dueDate ? TaskDueDate::fromString($dueDate) : null;
    }

    public function getTitle(): TaskTitle
    {
        return $this->title;
    }

    public function getDescription(): TaskDescription
    {
        return $this->description;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function getDueDate(): ?TaskDueDate
    {
        return $this->dueDate;
    }
}
