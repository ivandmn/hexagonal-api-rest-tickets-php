<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\ListUsers;

use App\Application\Query\ListUsers\ListUsersQuery;
use PHPUnit\Framework\TestCase;

final class ListUsersQueryTest extends TestCase
{
    public function test_can_instantiate_request(): void
    {
        $req = new ListUsersQuery();
        $this->assertInstanceOf(ListUsersQuery::class, $req);
    }
}
