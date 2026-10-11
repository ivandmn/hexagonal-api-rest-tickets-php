<?php

declare(strict_types=1);

namespace App\Domain\Exception\User;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\User\UserId;

final class UserNotFoundException extends DomainException
{
    public const ERROR_CODE = 'USER_NOT_FOUND';

    public function __construct(UserId $userId, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('User %s does not exist', $userId->value()),
            ['userId' => $userId->value()],
            0,
            $previous,
        );
    }
}
