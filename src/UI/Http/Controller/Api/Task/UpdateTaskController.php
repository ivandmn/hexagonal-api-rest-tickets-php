<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\Task\UpdateTask\UpdateTaskCommand;
use App\Application\DTO\Views\Task\TaskView;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\MalformedBodyResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Request\Payload;
use App\UI\Http\Response\ApiResponder;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Tasks')]
final class UpdateTaskController extends ApiController
{
    #[OA\Patch(
        summary: 'Update a task',
        description: 'Partial update: only the fields sent change; send `"due_date": null` to remove the due date. Allowed status transitions: `pending` → `in_progress` → `completed`.',
    )]
    #[OA\Parameter(name: 'id', description: 'Task identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        description: 'Partial update: omitted (or null) fields stay as they are; `"due_date": null` removes it.',
        properties: [
            new OA\Property(property: 'title', type: 'string', maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in', nullable: true),
            new OA\Property(property: 'status', ref: new Model(type: TaskStatus::class), nullable: true),
            new OA\Property(property: 'priority', ref: new Model(type: TaskPriority::class), nullable: true),
            new OA\Property(property: 'due_date', description: 'RFC 3339 date-time; a new due date cannot be in the past. `null` removes the due date; omit the field to keep it.', type: 'string', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true),
        ],
    ))]
    #[SuccessResponse(Task::class, 'Task updated')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'Task not found (also for an id that is not a UUID)', 'TASK_NOT_FOUND', 'Task 3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63 does not exist.', ['task_id' => '3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63'])]
    #[ProblemResponse(
        Response::HTTP_CONFLICT,
        'Status transition not allowed',
        'TASK_STATUS_TRANSITION_NOT_ALLOWED',
        'A task cannot move from "completed" to "pending".',
        ['from' => 'completed', 'to' => 'pending'],
        [['field' => 'status', 'code' => 'TASK_STATUS_TRANSITION_NOT_ALLOWED', 'message' => 'A task cannot move from "completed" to "pending".', 'params' => ['from' => 'completed', 'to' => 'pending']]],
    )]
    #[ValidationErrorResponse([
        ['field' => 'priority', 'code' => 'VALUE_INVALID_CHOICE', 'message' => 'This value must be one of: low, medium, high.', 'params' => ['allowed' => 'low, medium, high']],
        ['field' => 'due_date', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00).', 'params' => ['expected' => 'RFC3339 date-time (e.g. 2026-12-31T18:00:00+00:00)']],
    ])]
    #[MalformedBodyResponse]
    #[InternalErrorResponse]
    #[Route('/tasks/{id}', name: 'api_task_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function __invoke(string $id, Request $request, ApiResponder $responder): JsonResponse
    {
        $payload = Payload::fromJsonBody($request, ['title', 'description', 'status', 'priority', 'due_date']);
        $dueDate = $payload->optionalString('due_date');

        /** @var TaskView $task */
        $task = $this->dispatch(new UpdateTaskCommand(
            $id,
            $payload->optionalString('title'),
            $payload->optionalString('description'),
            $payload->optionalString('status'),
            $payload->optionalString('priority'),
            $dueDate,
            // `"due_date": null` removes it; leaving the key out keeps it
            $payload->has('due_date') && null === $dueDate,
        ));

        return $responder->ok($task);
    }
}
