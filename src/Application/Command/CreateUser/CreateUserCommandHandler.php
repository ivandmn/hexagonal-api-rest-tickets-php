<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Factory\UserFactory;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserName;

final readonly class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserFactory $userFactory,
        private UserRepositoryInterface $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateUserCommand $request): CreateUserResponse
    {
        $emailUser = UserEmail::fromString($request->getEmail());
        $nameUser  = UserName::fromString($request->getName());

        if ($this->userRepository->findByEmail($emailUser)) {
            throw new UserAlreadyExistsException($emailUser);
        }

        $user = $this->userFactory->register(
            $emailUser,
            $nameUser,
        );

        $this->userRepository->save($user);

        $this->logger->info('User Created', ['user_id' => $user->getId()->value(), 'user_email' => $user->getEmail()->value()]);

        return new CreateUserResponse($user);
    }
}
