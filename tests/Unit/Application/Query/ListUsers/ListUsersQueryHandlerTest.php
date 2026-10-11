<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\ListUsers;

use App\Application\Query\ListUsers\ListUsersQuery;
use App\Application\Query\ListUsers\ListUsersQueryHandler;
use App\Application\Query\ListUsers\ListUsersResponse;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;

final class ListUsersQueryHandlerTest extends TestCase
{
    public function test_returns_response_with_all_users(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);

        $users = [
            new User(
                UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000111'),
                UserEmail::fromString('u1@example.com'),
                UserName::fromString('User One'),
                new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
            ),
            new User(
                UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000222'),
                UserEmail::fromString('u2@example.com'),
                UserName::fromString('User Two'),
                new \DateTimeImmutable('2030-02-01T00:00:00+00:00'),
            ),
        ];

        $repo->expects($this->once())
            ->method('findAll')
            ->willReturn($users)
        ;

        $uc = new ListUsersQueryHandler($repo);

        $response = $uc(new ListUsersQuery());

        $this->assertInstanceOf(ListUsersResponse::class, $response);
        $payload = $response->toArray();

        $this->assertSame(2, $payload['meta']['total']);
        $this->assertSame('u1@example.com', $payload['data'][0]['email']);
        $this->assertSame('u2@example.com', $payload['data'][1]['email']);
    }
}
