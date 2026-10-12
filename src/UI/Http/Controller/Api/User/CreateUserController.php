<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\User\CreateUser\CreateUserCommand;
use App\Application\DTO\Views\User\UserView;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\User\UserId;
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

#[OA\Tag(name: 'Users')]
final class CreateUserController extends ApiController
{
    #[OA\Post(summary: 'Create a user')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'name'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: Email::MAX_LENGTH, example: 'jane.doe@example.com'),
            new OA\Property(property: 'name', type: 'string', maxLength: UserName::MAX_LENGTH, minLength: UserName::MIN_LENGTH, example: 'Jane Doe'),
        ],
    ))]
    #[SuccessResponse(User::class, 'User created', Response::HTTP_CREATED, location: true)]
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
    #[MalformedBodyResponse]
    #[InternalErrorResponse]
    #[Route('/users', name: 'api_user_create', methods: ['POST'])]
    public function __invoke(Request $request, ApiResponder $responder): JsonResponse
    {
        $payload = Payload::fromJsonBody($request, ['email', 'name']);

        /** @var UserView $user */
        $user = $this->dispatch(new CreateUserCommand(UserId::new()->value(), $payload->string('email'), $payload->string('name')));

        return $responder->created($user, 'api_user_detail', ['id' => $user->id]);
    }
}
