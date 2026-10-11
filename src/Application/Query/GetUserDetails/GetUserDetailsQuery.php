<?php

declare(strict_types=1);

namespace App\Application\Query\GetUserDetails;

use App\Application\Query\QueryInterface;
use App\Application\Validation\Field;
use App\Domain\ValueObject\User\UserId;

final readonly class GetUserDetailsQuery implements QueryInterface
{
    private UserId $userId;

    public function __construct(string $userId)
    {
        $this->userId = Field::of('userId', static fn (): UserId => UserId::fromString($userId));
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }
}
