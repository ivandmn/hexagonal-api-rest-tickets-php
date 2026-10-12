<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\Task\CreateTask\CreateTaskCommand;
use App\Application\DTO\Views\Task\TaskView;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\MalformedBodyResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Request\Payload;
use App\UI\Http\Response\ApiResponder;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tasks')]
final class CreateTaskController extends ApiController
{
    #[OA\Post(summary: 'Create a task', description: 'New tasks start with status `pending`.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['title', 'description', 'priority'],
        properties: [
            new OA\Property(property: 'title', type: 'string', maxLength: TaskTitle::MAX_LENGTH, minLength: TaskTitle::MIN_LENGTH, example: 'Fix login redirect'),
            new OA\Property(property: 'description', type: 'string', maxLength: TaskDescription::MAX_LENGTH, example: 'Users are redirected to a 404 after logging in'),
            new OA\Property(property: 'priority', ref: new Model(type: TaskPriority::class)),
            new OA\Property(property: 'due_date', description: 'RFC 3339 date-time; cannot be in the past', type: 'string', format: 'date-time', example: '2026-12-31T18:00:00+00:00', nullable: true),
        ],
    ))]
    #[SuccessResponse(Task::class, 'Task created', Response::HTTP_CREATED, location: true)]
    #[ProblemResponse(
        Response::HTTP_UNPROCESSABLE_ENTITY,
        'Due date in the past (TASK_DUE_DATE_IN_PAST) or invalid fields (VALIDATION_ERROR)',
        'TASK_DUE_DATE_IN_PAST',
        'The due date cannot be in the past.',
        ['due_date' => '2020-01-01T10:00:00+00:00'],
        [['field' => 'due_date', 'code' => 'TASK_DUE_DATE_IN_PAST', 'message' => 'The due date cannot be in the past.', 'params' => ['due_date' => '2020-01-01T10:00:00+00:00']]],
    )]
    #[MalformedBodyResponse]
    #[InternalErrorResponse]
    #[Route('/tasks', name: 'api_task_create', methods: ['POST'])]
    public function __invoke(Request $request, ApiResponder $responder): JsonResponse
    {
        $payload = Payload::fromJsonBody($request, ['title', 'description', 'priority', 'due_date']);

        /** @var TaskView $task */
        $task = $this->dispatch(new CreateTaskCommand(
            TaskId::new()->value(),
            $payload->string('title'),
            $payload->string('description'),
            $payload->string('priority'),
            $payload->optionalString('due_date'),
        ));

        return $responder->created($task, 'api_task_detail', ['id' => $task->id]);
    }
}
