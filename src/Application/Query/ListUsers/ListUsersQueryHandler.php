<?php

declare(strict_types=1);

namespace App\Application\Query\ListUsers;

use App\Application\Query\QueryHandlerInterface;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class ListUsersQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function __invoke(ListUsersQuery $request): ListUsersResponse
    {
        $users = $this->userRepository->findAll();

        return new ListUsersResponse($users);
    }
}
