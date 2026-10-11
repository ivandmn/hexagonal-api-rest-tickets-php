<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request\Task;

use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Infrastructure\Http\Request\ValidationMessages;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaskRequestDto
{
    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect')]
    public $title;

    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in')]
    public $description;

    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(ref: new Model(type: TaskPriority::class), example: 'high')]
    public $priority;

    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(description: 'Must be in the future', type: 'string', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true)]
    public $dueDate;
}
