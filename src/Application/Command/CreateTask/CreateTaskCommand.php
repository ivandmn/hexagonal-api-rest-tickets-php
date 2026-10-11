<?php

declare(strict_types=1);

namespace App\Application\Command\CreateTask;

use App\Application\Command\CommandInterface;
use App\Application\Exception\InvalidDateFormat;
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
        $date = null !== $dueDate ? \DateTimeImmutable::createFromFormat(TaskDueDate::FORMAT, $dueDate) : null;
        if (false === $date) {
            throw new InvalidDateFormat(\sprintf('Invalid Due date, must be in format "%s"', TaskDueDate::FORMAT_NAME));
        }

        $this->dueDate = $date;
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
