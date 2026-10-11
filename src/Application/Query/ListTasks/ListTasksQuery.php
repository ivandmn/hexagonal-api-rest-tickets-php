<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Application\Query\QueryInterface;

final readonly class ListTasksQuery implements QueryInterface
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 100;

    private int $page;
    private int $limit;

    // TODO: Add more filters
    public function __construct(
        private ?string $status,
        private ?string $priority,
        int $page = 1,
        int $limit = self::DEFAULT_LIMIT,
    ) {
        // The HTTP layer validates the range; normalise anyway so a bad value can never reach the repository.
        $this->page  = \max(1, $page);
        $this->limit = \min(self::MAX_LIMIT, \max(1, $limit));
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPriority(): ?string
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
