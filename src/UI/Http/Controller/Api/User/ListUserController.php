<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\DTO\Views\User\UserListView;
use App\Application\Query\User\ListUsers\ListUsersQuery;
use App\UI\Http\Controller\Api\ApiController;
use App\UI\Http\OpenApi\Attribute\CollectionResponse;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Schema\User;
use App\UI\Http\OpenApi\Schema\UserListMeta;
use App\UI\Http\Response\ApiResponder;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Users')]
final class ListUserController extends ApiController
{
    #[OA\Get(summary: 'List users', description: 'No users is a 200 with an empty `data`.')]
    #[CollectionResponse(User::class, UserListMeta::class, 'Every user')]
    #[InternalErrorResponse]
    #[Route('/api/users', name: 'api_user_list', methods: ['GET'])]
    public function __invoke(ApiResponder $responder): JsonResponse
    {
        /** @var UserListView $users */
        $users = $this->ask(new ListUsersQuery());

        return $responder->collection($users->items, ['total' => $users->total]);
    }
}
