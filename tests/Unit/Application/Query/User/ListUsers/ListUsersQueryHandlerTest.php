<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\User\ListUsers;

use App\Application\DTO\Views\User\UserView;
use App\Application\Query\User\ListUsers\ListUsersQuery;
use App\Application\Query\User\ListUsers\ListUsersQueryHandler;
use App\Tests\Support\Double\InMemoryUserRepository;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class ListUsersQueryHandlerTest extends TestCase
{
    public function test_returns_every_user_and_the_total(): void
    {
        $one = Mother::user(email: 'u1@example.com');
        $two = Mother::user(email: 'u2@example.com');

        $view = new ListUsersQueryHandler(new InMemoryUserRepository($one, $two))(new ListUsersQuery());

        $this->assertSame(2, $view->total);
        $this->assertSame([$one->getId()->value(), $two->getId()->value()], \array_map(static fn (UserView $user): string => $user->id, $view->items));
    }

    public function test_no_users_is_an_empty_list(): void
    {
        $view = new ListUsersQueryHandler(new InMemoryUserRepository())(new ListUsersQuery());

        $this->assertSame([], $view->items);
        $this->assertSame(0, $view->total);
    }
}
