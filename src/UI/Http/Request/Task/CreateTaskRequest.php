<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Task;

use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;
use App\UI\Http\Validation\ValidVO;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['title', 'description', 'priority'])]
final readonly class CreateTaskRequest
{
    public function __construct(
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(TaskTitle::class)])]
        #[OA\Property(maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect')]
        public ?string $title = null,
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(TaskDescription::class)])]
        #[OA\Property(maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in')]
        public ?string $description = null,
        #[Assert\Sequentially([new Assert\NotBlank(), new ValidVO(TaskPriority::class, factory: 'fromString')])]
        #[OA\Property(ref: new Model(type: TaskPriority::class))]
        public ?string $priority = null,
        #[ValidVO(TaskDueDate::class, factory: 'fromString')]
        #[OA\Property(description: 'RFC 3339 date-time, must be in the future', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true)]
        public ?string $dueDate = null,
    ) {
    }

    /**
     * Only called once validated: required fields are present.
     */
    public function toCommand(): CreateTaskCommand
    {
        return new CreateTaskCommand((string) $this->title, (string) $this->description, (string) $this->priority, $this->dueDate);
    }
}
