<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\GetUserDetails;

use App\Application\Query\GetUserDetails\GetUserDetailsQuery;
use App\Application\Query\GetUserDetails\GetUserDetailsQueryHandler;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class GetUserDetailsQueryHandlerTest extends TestCase
{
    use AssertsDomainError;

    public function test_returns_the_view_of_the_user(): void
    {
        $user = Mother::user();

        $view = new GetUserDetailsQueryHandler(new InMemoryUserRepository($user))(new GetUserDetailsQuery($user->getId()->value()));

        $this->assertSame($user->getId()->value(), $view->id);
        $this->assertSame($user->getEmail()->value(), $view->email);
    }

    public function test_fails_when_the_user_does_not_exist(): void
    {
        $userId  = UserId::new()->value();
        $handler = new GetUserDetailsQueryHandler(new InMemoryUserRepository());

        $this->assertDomainError(UserNotFoundException::ERROR_CODE, static fn () => $handler(new GetUserDetailsQuery($userId)), ['user_id' => $userId]);
    }

    public function test_the_query_rejects_an_invalid_id(): void
    {
        $this->assertFieldError('userId', InvalidValueException::INVALID_FORMAT, static fn () => new GetUserDetailsQuery('not-a-uuid'));
    }
}
