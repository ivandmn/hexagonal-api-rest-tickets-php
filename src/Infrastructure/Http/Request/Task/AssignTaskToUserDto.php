<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request\Task;

use App\Infrastructure\Http\Request\ValidationMessages;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final class AssignTaskToUserDto
{
    #[Assert\NotBlank(message: ValidationMessages::REQUIRED)]
    #[Assert\Type(type: 'string', message: ValidationMessages::TYPE)]
    #[OA\Property(type: 'string', format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
    public $userId;
}
