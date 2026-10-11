<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\DTO\Views\UserView;
use App\Application\Query\GetUserDetails\GetUserDetailsQuery;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\ProblemResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\User;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'Users')]
final class GetUserController extends ApiController
{
    #[OA\Get(summary: 'Get user details')]
    #[OA\Parameter(name: 'id', description: 'User identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[SuccessResponse(User::class, 'User details')]
    #[ProblemResponse(Response::HTTP_NOT_FOUND, 'User not found (also for an id that is not a UUID)', 'USER_NOT_FOUND', 'User b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e does not exist.', ['user_id' => 'b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e'])]
    #[InternalErrorResponse]
    #[Route('/api/users/{id}', name: 'api_user_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, ApiResponder $responder): JsonResponse
    {
        /** @var UserView $user */
        $user = $this->ask(new GetUserDetailsQuery($id));

        return $responder->ok($user);
    }
}
