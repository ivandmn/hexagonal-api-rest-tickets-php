<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request\Task;

use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Infrastructure\Http\Request\ValidationMessages;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTaskRequestDto
{
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect', nullable: true)]
    public $title;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in', nullable: true)]
    public $description;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(ref: new Model(type: TaskStatus::class), example: 'in_progress', nullable: true)]
    public $status;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(ref: new Model(type: TaskPriority::class), example: 'medium', nullable: true)]
    public $priority;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(description: 'Must be in the future. `null` removes the due date; omit the field to keep it.', type: 'string', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true)]
    public $dueDate;
}
