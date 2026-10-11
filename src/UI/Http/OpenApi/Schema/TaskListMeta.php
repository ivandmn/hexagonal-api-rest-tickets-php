<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['total', 'page', 'limit'])]
final class TaskListMeta
{
    #[OA\Property(description: 'Tasks matching the filters, across all pages', example: 1)]
    public int $total;

    #[OA\Property(example: 1)]
    public int $page;

    #[OA\Property(example: 50)]
    public int $limit;
}
