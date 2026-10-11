<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\User\UpdateUser\UpdateUserCommand;
use App\Application\DTO\Views\User\UserView;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\User\UserName;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\MalformedBodyResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Attribute\ValidationErrorResponse;
use App\UI\Http\OpenApi\Schema\User;
use App\UI\Http\Request\Payload;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Users')]
final class UpdateUserController extends ApiController
{
    #[OA\Patch(summary: 'Update a user', description: 'Partial update: only the fields sent change.')]
    #[OA\Parameter(name: 'id', description: 'User identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        description: 'Partial update: omitted (or null) fields stay as they are.',
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: Email::MAX_LENGTH, example: 'jane.doe@example.com', nullable: true),
            new OA\Property(property: 'name', type: 'string', maxLength: UserName::MAX_LENGTH, minLength: UserName::MIN_LENGTH, example: 'Jane Doe', nullable: true),
        ],
    ))]
    #[SuccessResponse(User::class, 'User updated')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'User not found (also for an id that is not a UUID)', 'USER_NOT_FOUND', 'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e does not exist.', ['user_id' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e'])]
    #[ProblemResponse(
        Response::HTTP_CONFLICT,
        'Email already registered by another user',
        'USER_ALREADY_EXISTS',
        'A user with email jane.doe@example.com already exists.',
        ['email' => 'jane.doe@example.com'],
        [['field' => 'email', 'code' => 'USER_ALREADY_EXISTS', 'message' => 'A user with email jane.doe@example.com already exists.', 'params' => ['email' => 'jane.doe@example.com']]],
    )]
    #[ValidationErrorResponse([
        ['field' => 'email', 'code' => 'VALUE_INVALID_FORMAT', 'message' => 'Invalid format, expected email address.', 'params' => ['expected' => 'email address']],
        ['field' => 'name', 'code' => 'VALUE_TOO_SHORT', 'message' => 'This value must be at least 5 characters long.', 'params' => ['min' => 5]],
    ])]
    #[MalformedBodyResponse]
    #[InternalErrorResponse]
    #[Route('/api/users/{id}', name: 'api_user_update', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function __invoke(string $id, Request $request, ApiResponder $responder): JsonResponse
    {
        $payload = Payload::fromJsonBody($request, ['email', 'name']);

        /** @var UserView $user */
        $user = $this->dispatch(new UpdateUserCommand($id, $payload->optionalString('email'), $payload->optionalString('name')));

        return $responder->ok($user);
    }
}
