<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Task;

use App\Application\Query\ListTasks\ListTasksQuery;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\UI\Http\Validation\ValidVO;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of GET /api/tasks (documented on the controller as query parameters).
 */
final readonly class ListTasksRequest
{
    public function __construct(
        #[ValidVO(TaskStatus::class, factory: 'fromString')]
        public ?string $status = null,
        #[ValidVO(TaskPriority::class, factory: 'fromString')]
        public ?string $priority = null,
        #[Assert\Range(min: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: ListTasksQuery::MAX_LIMIT)]
        public int $limit = ListTasksQuery::DEFAULT_LIMIT,
    ) {
    }

    public function toQuery(): ListTasksQuery
    {
        return new ListTasksQuery($this->status, $this->priority, $this->page, $this->limit);
    }
}
