<?php

declare(strict_types=1);

namespace App\Application\Query\User\ListUsers;

use App\Application\DTO\Views\User\UserListView;
use App\Application\DTO\Views\User\UserView;
use App\Application\Query\QueryHandlerInterface;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class ListUsersQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(ListUsersQuery $query): UserListView
    {
        $users = \array_map(UserView::fromUser(...), $this->userRepository->findAll());

        return new UserListView($users, \count($users));
    }
}
