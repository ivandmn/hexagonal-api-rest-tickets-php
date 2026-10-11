<?php

declare(strict_types=1);

namespace App\UI\Http\Request\Task;

use App\Application\Command\UpdateTask\UpdateTaskCommand;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\UI\Http\Validation\ValidVO;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Partial update: omitted (or null) fields stay as they are; `"dueDate": null` removes it.
 */
final readonly class UpdateTaskRequest
{
    public function __construct(
        #[ValidVO(TaskTitle::class)]
        #[OA\Property(maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect', nullable: true)]
        public ?string $title = null,
        #[ValidVO(TaskDescription::class)]
        #[OA\Property(maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in', nullable: true)]
        public ?string $description = null,
        #[ValidVO(TaskStatus::class, factory: 'fromString')]
        #[OA\Property(ref: new Model(type: TaskStatus::class), nullable: true)]
        public ?string $status = null,
        #[ValidVO(TaskPriority::class, factory: 'fromString')]
        #[OA\Property(ref: new Model(type: TaskPriority::class), nullable: true)]
        public ?string $priority = null,
        // Format only: re-sending the current due date must work even once it is in the past.
        #[ValidVO(TaskDueDate::class, factory: 'parse')]
        #[OA\Property(description: 'RFC 3339 date-time, must be in the future. `null` removes the due date; omit the field to keep it.', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true)]
        public ?string $dueDate = null,
    ) {
    }

    public function toCommand(string $taskId, bool $clearDueDate): UpdateTaskCommand
    {
        return new UpdateTaskCommand($taskId, $this->title, $this->description, $this->status, $this->priority, $this->dueDate, $clearDueDate);
    }
}
