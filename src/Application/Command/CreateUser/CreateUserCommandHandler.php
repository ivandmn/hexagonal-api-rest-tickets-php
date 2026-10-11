<?php

declare(strict_types=1);

namespace App\Application\Command\CreateUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\UserView;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateUserCommand $command): UserView
    {
        if (null !== $this->userRepository->findByEmail($command->getEmail())) {
            throw new UserAlreadyExistsException($command->getEmail());
        }

        $user = User::create($command->getUserId(), $command->getEmail(), $command->getName(), $this->clock->now());

        $this->userRepository->save($user);

        $this->logger->info('User created', ['user_id' => $user->getId()->value(), 'user_email' => $user->getEmail()->value()]);

        return UserView::fromUser($user);
    }
}
