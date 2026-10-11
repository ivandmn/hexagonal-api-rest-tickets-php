<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\CreateUser\CreateUserResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\User;
use App\UI\Http\Request\PayloadContext;
use App\UI\Http\Request\User\CreateUserRequest;
use App\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users')]
final class CreateUserController extends BaseController
{
    #[OA\Post(summary: 'Create a user')]
    #[OA\RequestBody(required: true, content: new Model(type: CreateUserRequest::class))]
    #[SuccessResponse(User::class, 'User created')]
    #[ProblemResponse(
        Response::HTTP_CONFLICT,
        'Email already registered',
        'USER_ALREADY_EXISTS',
        'A user with email jane.doe@example.com already exists.',
        ['email' => 'jane.doe@example.com'],
        [['field' => 'email', 'code' => 'USER_ALREADY_EXISTS', 'message' => 'A user with email jane.doe@example.com already exists.', 'params' => ['email' => 'jane.doe@example.com']]],
    )]
    #[ValidationErrorResponse([
        ['field' => 'email', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected email address.', 'params' => ['expected' => 'email address']],
        ['field' => 'name', 'code' => 'VALUE_REQUIRED', 'message' => 'This value is required.', 'params' => []],
    ])]
    #[InternalErrorResponse]
    #[Route('/api/users', name: 'api_user_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'json', serializationContext: PayloadContext::STRICT)]
        CreateUserRequest $payload,
    ): JsonResponse {
        /** @var CreateUserResponse $response */
        $response = $this->commandHandler($payload->toCommand());

        return ApiResponse::success($response);
    }
}
