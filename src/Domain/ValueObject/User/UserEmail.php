<?php

declare(strict_types=1);

namespace App\Domain\ValueObject\User;

use App\Domain\ValueObject\Email;

final readonly class UserEmail extends Email
{
    public function __construct(string $value)
    {
        parent::__construct(\strtolower($value));
    }
}
