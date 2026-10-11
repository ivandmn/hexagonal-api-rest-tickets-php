<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Application\Query\QueryHandlerInterface;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;

final readonly class ListTasksQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(ListTasksQuery $request): ListTasksResponse
    {
        $filters = [];

        if ($request->getStatus()) {
            $filters['status'] = TaskStatus::fromString($request->getStatus())->value;
        }

        if ($request->getPriority()) {
            $filters['priority'] = TaskPriority::fromString($request->getPriority())->value;
        }

        return new ListTasksResponse(
            $this->taskRepository->findByFilters($filters, $request->getPage(), $request->getLimit()),
            $this->taskRepository->countByFilters($filters),
            $request->getPage(),
            $request->getLimit(),
        );
    }
}
