<?php

declare(strict_types=1);

namespace App\Application\DTO\Views\Task;

/**
 * One page of tasks plus what is needed to paginate.
 */
final readonly class TaskListView
{
    /**
     * @param list<TaskView> $items
     * @param int            $total tasks matching the filters, across all pages
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }
}
