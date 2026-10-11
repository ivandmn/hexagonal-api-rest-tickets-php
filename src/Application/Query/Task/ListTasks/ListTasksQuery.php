<?php

declare(strict_types=1);

namespace App\Application\Query\Task\ListTasks;

use App\Application\Query\QueryInterface;
use App\Application\Validation\Field;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;

final readonly class ListTasksQuery implements QueryInterface
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 100;

    private ?TaskStatus $status;
    private ?TaskPriority $priority;
    private int $page;
    private int $limit;

    // TODO: Add more filters
    public function __construct(?string $status, ?string $priority, int $page = 1, int $limit = self::DEFAULT_LIMIT)
    {
        $this->status   = null !== $status ? Field::of('status', static fn (): TaskStatus => TaskStatus::fromString($status)) : null;
        $this->priority = null !== $priority ? Field::of('priority', static fn (): TaskPriority => TaskPriority::fromString($priority)) : null;
        $this->page     = Field::of('page', static fn (): int => $page >= 1 ? $page : throw InvalidValueException::belowMinimum(1, $page));
        $this->limit    = Field::of('limit', static fn (): int => $limit >= 1 && $limit <= self::MAX_LIMIT ? $limit : throw InvalidValueException::outOfRange(1, self::MAX_LIMIT, $limit));
    }

    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): ?TaskPriority
    {
        return $this->priority;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
