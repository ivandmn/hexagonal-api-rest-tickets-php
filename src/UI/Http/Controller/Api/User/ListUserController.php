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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ListUserController extends BaseController
{
    public function __construct(
        QueryBusInterface $queryBus,
        CommandBusInterface $commandBus,
        private readonly MonologLogger $logger,
    ) {
        parent::__construct($queryBus, $commandBus);
    }

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
