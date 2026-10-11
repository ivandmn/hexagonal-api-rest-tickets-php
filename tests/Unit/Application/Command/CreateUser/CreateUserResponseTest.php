<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateUser;

use App\Application\Command\CreateUser\CreateUserResponse;
use App\Domain\Model\User;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;

final class CreateUserResponseTest extends TestCase
{
    public function test_json_serialize_returns_expected_payload(): void
    {
        $id      = UserId::fromString('018f9f9a-aaaa-bbbb-cccc-000000000123');
        $email   = UserEmail::fromString('user@example.com');
        $name    = UserName::fromString('User Name');
        $created = new \DateTimeImmutable('2030-01-01T00:00:00+00:00');

        $user = new User($id, $email, $name, $created);

        $response = new CreateUserResponse($user);

        $this->assertInstanceOf(\JsonSerializable::class, $response);

        $data = $response->jsonSerialize();

        $this->assertSame($id->value(), $data['id']);
        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame('User Name', $data['name']);
        $this->assertSame('2030-01-01T00:00:00+00:00', $data['createdAt']);
    }

    public function test_to_array_matches_json_serialize(): void
    {
        $user = new User(
            UserId::fromString('018f9f9a-dead-beef-cafe-000000000999'),
            UserEmail::fromString('another@example.com'),
            UserName::fromString('Another User'),
            new \DateTimeImmutable('2031-02-03T04:05:06+00:00')
        );

        $response = new CreateUserResponse($user);

        $this->assertSame($response->jsonSerialize(), $response->toArray());
    }
}
