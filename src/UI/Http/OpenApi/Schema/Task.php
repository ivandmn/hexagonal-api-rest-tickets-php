<?php

declare(strict_types=1);

namespace App\UI\Http\OpenApi\Schema;

use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'title', 'description', 'status', 'priority', 'assignedTo', 'dueDate', 'createdAt', 'updatedAt'])]
final class Task
{
    #[OA\Property(format: 'uuid', example: '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63')]
    public string $id;

    #[OA\Property(maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect')]
    public string $title;

    #[OA\Property(maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in')]
    public string $description;

    #[OA\Property(ref: new Model(type: TaskStatus::class), example: 'pending')]
    public TaskStatus $status;

    #[OA\Property(ref: new Model(type: TaskPriority::class), example: 'high')]
    public TaskPriority $priority;

    #[OA\Property(ref: new Model(type: AssignedUser::class), nullable: true)]
    public ?AssignedUser $assignedTo;

    #[OA\Property(format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true)]
    public ?string $dueDate;

    #[OA\Property(format: 'date-time', example: '2026-10-11T08:00:00+00:00')]
    public string $createdAt;

    #[OA\Property(format: 'date-time', nullable: true)]
    public ?string $updatedAt;
}
