<?php

declare(strict_types=1);

namespace App\Application\Command\DeleteTask;

use App\Domain\Model\Task;

final readonly class DeleteTaskResponse implements \JsonSerializable
{
    public function __construct(private Task $task)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'message' => \sprintf('Task "%s" has been successfully deleted', $this->task->getTitle()->value()),
            'task_id' => $this->task->getId()->value(),
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
