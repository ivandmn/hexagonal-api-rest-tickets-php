<?php

declare(strict_types=1);

namespace App\Domain\Exception\User;

use App\Domain\Exception\DomainException;
use App\Domain\ValueObject\User\UserEmail;

final class UserAlreadyExistsException extends DomainException
{
    public const ERROR_CODE = 'USER_ALREADY_EXISTS';

    public function __construct(UserEmail $email, ?\Throwable $previous = null)
    {
        parent::__construct(
            self::ERROR_CODE,
            \sprintf('A user with email %s already exists', $email->value()),
            ['email' => $email->value()],
            0,
            $previous,
        );
    }
}
