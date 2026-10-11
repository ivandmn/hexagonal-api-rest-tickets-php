<?php

declare(strict_types=1);

namespace App\Application\Query\ListTasks;

use App\Application\Query\QueryHandlerInterface;
use App\Domain\Repository\TaskRepositoryInterface;

final readonly class ListTasksQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function __invoke(ListTasksQuery $query): ListTasksResponse
    {
        $filters = \array_filter(
            ['status' => $query->getStatus(), 'priority' => $query->getPriority()],
            static fn (?\BackedEnum $value): bool => null !== $value,
        );

        return new ListTasksResponse(
            $this->taskRepository->findByFilters($filters, $query->getPage(), $query->getLimit()),
            $this->taskRepository->countByFilters($filters),
            $query->getPage(),
            $query->getLimit(),
        );
    }
}
