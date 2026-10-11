<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Task;

use App\Application\Command\AssignTaskToUser\AssignTaskToUserCommand;
use App\Domain\ValueObject\User\UserId;
use App\UI\Http\Validation\ValidVO;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['userId'])]
final readonly class AssignTaskRequest
{
    public function __construct(
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(UserId::class)])]
        #[OA\Property(format: 'uuid', example: 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e')]
        public ?string $userId = null,
    ) {
    }

    public function toCommand(string $taskId): AssignTaskToUserCommand
    {
        return new AssignTaskToUserCommand($taskId, (string) $this->userId);
    }
}
