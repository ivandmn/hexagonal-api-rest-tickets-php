<?php

declare(strict_types=1);

namespace App\Application\Query\GetTaskDetails;

use App\Application\Query\QueryInterface;

final readonly class GetTaskDetailsQuery implements QueryInterface
{
    public function __construct(
        private string $taskId
    ) {
    }

    public function getTaskId(): string
    {
        return $this->taskId;
    }
}
