<?php

declare(strict_types=1);

namespace App\Application\Query\GetUserDetails;

use App\Application\DTO\Views\UserView;
use App\Application\Query\QueryHandlerInterface;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;

final readonly class GetUserDetailsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(GetUserDetailsQuery $query): UserView
    {
        $user = $this->userRepository->findById($query->getUserId()) ?? throw new UserNotFoundException($query->getUserId());

        return UserView::fromUser($user);
    }
}
