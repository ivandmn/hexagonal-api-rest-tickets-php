<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Application\Query\QueryInterface;
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
        $this->status   = null !== $status ? TaskStatus::fromString($status) : null;
        $this->priority = null !== $priority ? TaskPriority::fromString($priority) : null;
        // The HTTP layer validates the range; normalise anyway so a bad value can never reach the repository.
        $this->page  = \max(1, $page);
        $this->limit = \min(self::MAX_LIMIT, \max(1, $limit));
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
