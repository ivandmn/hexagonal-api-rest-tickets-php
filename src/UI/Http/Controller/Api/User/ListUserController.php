<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersResponse;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\UserList;
use App\UI\Http\Response\ApiResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users')]
final class ListUserController extends BaseController
{
    #[OA\Get(summary: 'List users')]
    #[SuccessResponse(UserList::class, 'Users found')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'There are no users')]
    #[InternalErrorResponse]
    #[Route('/api/users', name: 'api_user_list', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        /** @var ListUsersResponse $response */
        $response = $this->queryHandler(new ListUsersQuery());

        return $response->isEmpty() ? ApiResponse::empty() : ApiResponse::success($response);
    }
}
