<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Summary of the user a task is assigned to', required: ['id', 'name'])]
final class AssignedUser
{
    #[OA\Property(format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
    public string $id;

    #[OA\Property(example: 'Jane Doe')]
    public string $name;
}
