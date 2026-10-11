<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['message', 'user_id', 'deleted_tasks'])]
final class UserDeletion
{
    #[OA\Property(example: 'User "jane.doe@example.com" has been successfully deleted')]
    public string $message;

    #[OA\Property(property: 'user_id', format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
    public string $user_id;

    #[OA\Property(property: 'deleted_tasks', description: 'Completed tasks deleted along with the user', example: 3)]
    public int $deleted_tasks;
}
