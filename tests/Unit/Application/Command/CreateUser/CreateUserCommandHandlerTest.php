<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateUser;

use App\Application\Command\CreateUser\CreateUserCommand;
use App\Application\Command\CreateUser\CreateUserCommandHandler;
use App\Application\Command\CreateUser\CreateUserResponse;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\User\InvalidUserEmailException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Factory\UserFactory;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class CreateUserCommandHandlerTest extends TestCase
{
    public function test_creates_user_persists_and_logs(): void
    {
        $repo   = $this->createMock(UserRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = new CreateUserCommand('user@example.com', 'User Name');

        $repo->expects($this->once())
            ->method('findByEmail')
            ->with($this->callback(static fn (UserEmail $e) => 'user@example.com' === $e->value()))
            ->willReturn(null);

        $repo->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (User $u) => 'user@example.com' === $u->getEmail()->value()
                    && 'User Name' === $u->getName()->value()));

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'User Created',
                $this->callback(static fn (array $ctx) => isset($ctx['user_id'], $ctx['user_email'])
                        && RamseyUuid::isValid($ctx['user_id'])
                        && 'user@example.com' === $ctx['user_email'])
            );

        $uc       = new CreateUserCommandHandler(new UserFactory(), $repo, $logger);
        $response = $uc($request);

        $this->assertInstanceOf(CreateUserResponse::class, $response);
        $data = $response->toArray();
        $this->assertTrue(RamseyUuid::isValid($data['id']));
        $this->assertSame('user@example.com', $data['email']);
        $this->assertSame('User Name', $data['name']);
        $this->assertNotEmpty($data['createdAt']);
    }

    public function test_throws_if_email_already_exists(): void
    {
        $repo   = $this->createMock(UserRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $existing = new User(
            UserId::new(),
            UserEmail::fromString('user@example.com'),
            UserName::fromString('Existing User'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00')
        );

        $repo->expects($this->once())
            ->method('findByEmail')
            ->willReturn($existing);

        $repo->expects($this->never())->method('save');
        $logger->expects($this->never())->method('info');

        $uc = new CreateUserCommandHandler(new UserFactory(), $repo, $logger);

        $this->expectException(UserAlreadyExistsException::class);
        $uc(new CreateUserCommand('user@example.com', 'User Name'));
    }

    public function test_propagates_invalid_email_exception(): void
    {
        $repo   = $this->createMock(UserRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $uc = new CreateUserCommandHandler(new UserFactory(), $repo, $logger);

        $this->expectException(InvalidUserEmailException::class);
        $uc(new CreateUserCommand('not-an-email', 'User Name'));
    }
}
