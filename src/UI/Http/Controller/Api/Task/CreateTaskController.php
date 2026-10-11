<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\Task;

use App\Application\Command\CreateTask\CreateTaskResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\Task;
use App\UI\Http\Request\PayloadContext;
use App\UI\Http\Request\Task\CreateTaskRequest;
use App\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Tasks')]
final class CreateTaskController extends BaseController
{
    #[OA\Post(summary: 'Create a task', description: 'New tasks start with status `pending`.')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateTaskRequest::class))]
    #[SuccessResponse(Task::class, 'Task created')]
    #[ProblemResponse(Response::HTTP_BAD_REQUEST, 'Malformed request body', 'REQUEST_MALFORMED_JSON', 'The request body is not valid JSON.')]
    #[ValidationErrorResponse([
        ['field' => 'title', 'code' => 'VALUE_TOO_SHORT', 'message' => 'This value must be at least 5 characters long.', 'params' => ['min' => 5]],
        ['field' => 'dueDate', 'code' => 'TASK_DUE_DATE_IN_PAST', 'message' => 'The due date cannot be in the past.', 'params' => ['dueDate' => '2020-01-01T10:00:00+00:00']],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/tasks', name: 'api_task_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', serializationContext: PayloadContext::STRICT)]
        CreateTaskRequest $payload,
    ): JsonResponse {
        /** @var CreateTaskResponse $response */
        $response = $this->commandHandler($payload->toCommand());

        return ApiResponse::success($response);
    }
}
