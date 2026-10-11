<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['total'])]
final class UserListMeta
{
    #[OA\Property(description: 'Number of users', example: 1)]
    public int $total;
}
