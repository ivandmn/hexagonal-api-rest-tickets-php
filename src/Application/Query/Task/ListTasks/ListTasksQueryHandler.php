<?php

declare(strict_types=1);

namespace App\Application\Query\Task\ListTasks;

use App\Application\DTO\Views\Task\TaskListView;
use App\Application\DTO\Views\Task\TaskView;
use App\Application\Query\QueryHandlerInterface;
use App\Domain\Repository\TaskCriteria;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class ListTasksQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(ListTasksQuery $query): TaskListView
    {
        $criteria = new TaskCriteria(
            $query->getStatus(),
            $query->getPriority(),
            $query->getAssignedTo(),
            $query->getDueFrom(),
            $query->getDueTo(),
        );
        $tasks    = $this->taskRepository->findByCriteria($criteria, $query->getPage(), $query->getLimit());

        return new TaskListView(
            \array_map(TaskView::fromTask(...), $tasks),
            $this->taskRepository->countByCriteria($criteria),
            $query->getPage(),
            $query->getLimit(),
        );
    }
}
