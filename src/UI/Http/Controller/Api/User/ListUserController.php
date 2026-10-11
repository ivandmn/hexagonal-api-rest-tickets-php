<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api\User;

use App\Application\Command\CommandBusInterface;
use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersResponse;
use App\Application\Query\QueryBusInterface;
use App\Infrastructure\Http\ApiResponse;
use App\Infrastructure\Service\MonologLogger;
use App\Infrastructure\Symfony\Controller\BaseController;
use App\UI\Http\OpenApi\Attribute\InternalErrorResponse;
use App\UI\Http\OpenApi\Attribute\SuccessResponse;
use App\UI\Http\OpenApi\Schema\UserList;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Users')]
class ListUserController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

    #[OA\Get(summary: 'List users')]
    #[SuccessResponse(UserList::class, 'Users found')]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'There are no users')]
    #[InternalErrorResponse]
    #[Route('/api/users', name: 'api_user_list', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            /** @var ListUsersResponse $response */
            $response = $this->queryHandler(new ListUsersQuery());

            return $response->isEmpty() ? ApiResponse::empty() : ApiResponse::success($response);
        } catch (\Throwable $exception) {
            $this->logger->error(
                \sprintf('Error Listing Users: %s', $exception->getMessage()),
                ['exception' => $exception]
            );

            return ApiResponse::internalError();
        }
    }
}
