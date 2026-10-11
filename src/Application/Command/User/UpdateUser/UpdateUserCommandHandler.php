<?php

declare(strict_types=1);

namespace App\Application\Command\User\UpdateUser;

use App\Application\Command\CommandHandlerInterface;
use App\Application\DTO\Views\User\UserView;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class UpdateUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws UserAlreadyExistsException when the new email belongs to another user
     */
    public function __invoke(UpdateUserCommand $command): UserView
    {
        $user = $this->userRepository->findById($command->getUserId()) ?? throw new UserNotFoundException($command->getUserId());
        $now  = $this->clock->now();

        if (null !== $email = $command->getEmail()) {
            $owner = $this->userRepository->findByEmail($email);
            if (null !== $owner && !$owner->equals($user)) {
                throw new UserAlreadyExistsException($email);
            }

            $user->changeEmail($email, $now);
        }

        if (null !== $name = $command->getName()) {
            $user->changeName($name, $now);
        }

        // Saving an unchanged user writes nothing; the repository still catches a concurrent
        // request taking the same email after the check above.
        $this->userRepository->save($user);

        $this->logger->info('User updated', ['user_id' => $user->getId()->value()]);

        return UserView::fromUser($user);
    }
}
