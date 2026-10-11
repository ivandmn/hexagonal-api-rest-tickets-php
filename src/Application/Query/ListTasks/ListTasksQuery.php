<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Application\Query\QueryInterface;

final class ListTasksQuery implements QueryInterface
{
    // TODO: Add more filters
    public function __construct(
        private ?string $status,
        private ?string $priority,
        private ?int $page = 1,
        private ?int $limit = 50,
    ) {
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
