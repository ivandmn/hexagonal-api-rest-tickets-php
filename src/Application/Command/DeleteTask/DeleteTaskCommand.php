<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteTask;

use App\Application\Command\CommandInterface;

final readonly class DeleteTaskCommand implements CommandInterface
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
