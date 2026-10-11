<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\ListUsers;

use App\Application\Query\ListUsers\ListUsersResponse;
use App\Domain\Model\User;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;

final class ListUsersResponseTest extends TestCase
{
    public function test_json_serialize_with_multiple_users_and_meta_and_iteration(): void
    {
        $u1 = new User(
            UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000111'),
            UserEmail::fromString('u1@example.com'),
            UserName::fromString('User One'),
            new \DateTimeImmutable('2030-01-01T10:00:00+00:00')
        );
        $u2 = new User(
            UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000222'),
            UserEmail::fromString('u2@example.com'),
            UserName::fromString('User Two'),
            new \DateTimeImmutable('2031-02-03T04:05:06+00:00')
        );

        $resp = new ListUsersResponse([$u1, $u2]);

        $this->assertInstanceOf(\JsonSerializable::class, $resp);

        $data = $resp->jsonSerialize();

        $this->assertCount(2, $data['data']);
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000111', $data['data'][0]['id']);
        $this->assertSame('u1@example.com', $data['data'][0]['email']);
        $this->assertSame('User One', $data['data'][0]['name']);
        $this->assertSame('2030-01-01T10:00:00+00:00', $data['data'][0]['createdAt']);

        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000222', $data['data'][1]['id']);
        $this->assertSame('u2@example.com', $data['data'][1]['email']);
        $this->assertSame('User Two', $data['data'][1]['name']);
        $this->assertSame('2031-02-03T04:05:06+00:00', $data['data'][1]['createdAt']);

        $this->assertSame(2, $data['meta']['total']);
        $this->assertSame(1, $data['meta']['page']);

        $this->assertSame($data, $resp->toArray());
        $this->assertFalse($resp->isEmpty());
        $this->assertSame(2, $resp->count());

        $collected = [];
        foreach ($resp as $user) {
            $collected[] = $user;
        }
        $this->assertSame([$u1, $u2], $collected);
    }

    public function test_json_serialize_with_empty_list_and_helpers(): void
    {
        $resp = new ListUsersResponse([]);

        $data = $resp->jsonSerialize();
        $this->assertSame(0, $data['meta']['total']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertTrue($resp->isEmpty());
        $this->assertSame([], \iterator_to_array($resp));
        $this->assertSame($data, $resp->toArray());
    }
}
