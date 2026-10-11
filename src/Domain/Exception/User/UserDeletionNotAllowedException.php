<?php

declare(strict_types=1);

namespace App\Domain\Exception\User;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\User\UserId;

final class UserDeletionNotAllowedException extends DomainException
{
    public const ERROR_CODE = 'USER_DELETION_NOT_ALLOWED';

    public function __construct(UserId $userId, int $unfinishedTasks, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('User %s cannot be deleted: %d task(s) not completed yet', $userId->value(), $unfinishedTasks),
            ['userId' => $userId->value(), 'unfinishedTasks' => $unfinishedTasks],
            0,
            $previous,
        );
    }
}
