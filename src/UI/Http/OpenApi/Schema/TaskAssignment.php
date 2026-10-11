<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['message', 'task_id', 'user_id'])]
final class TaskAssignment
{
    #[OA\Property(example: 'Task "Fix login redirect" has been assigned to "jane.doe@example.com"')]
    public string $message;

    #[OA\Property(property: 'task_id', format: 'uuid', example: '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63')]
    public string $task_id;

    #[OA\Property(property: 'user_id', format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
    public string $user_id;
}
