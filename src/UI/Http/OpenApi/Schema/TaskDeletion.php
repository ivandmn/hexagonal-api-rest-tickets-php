<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['message', 'task_id'])]
final class TaskDeletion
{
    #[OA\Property(example: 'Task "Fix login redirect" has been successfully deleted')]
    public string $message;

    #[OA\Property(property: 'task_id', format: 'uuid', example: '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63')]
    public string $task_id;
}
