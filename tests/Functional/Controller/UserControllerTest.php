<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsProblemResponse;
use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserControllerTest extends WebTestCase
{
    use AssertsProblemResponse;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    public function test_list_returns_204_when_empty(): void
    {
        $this->client->request('GET', '/api/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    public function test_list_returns_200_with_payload(): void
    {
        /** @var UserRepositoryInterface $repo */
        $repo = static::getContainer()->get(UserRepositoryInterface::class);

        $u1 = new User(
            UserId::new(),
            UserEmail::fromString('u1@example.com'),
            UserName::fromString('User One'),
            new \DateTimeImmutable('2030-01-01T10:00:00+00:00'),
        );
        $u2 = new User(
            UserId::new(),
            UserEmail::fromString('u2@example.com'),
            UserName::fromString('User Two'),
            new \DateTimeImmutable('2030-02-01T10:00:00+00:00'),
        );
        $repo->save($u1);
        $repo->save($u2);

        $this->client->request('GET', '/api/users');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertTrue(
            $this->client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'Response is not JSON',
        );
        $content = $this->client->getResponse()->getContent();
        $this->assertNotSame('', $content, 'Empty response body');

        $json = \json_decode($content, true);
        $this->assertIsArray($json, 'Invalid JSON response: ' . $content);

        $this->assertSame('success', $json['status']);
        $this->assertSame(2, $json['data']['meta']['total']);
        $this->assertSame('u1@example.com', $json['data']['data'][0]['email']);
        $this->assertSame('u2@example.com', $json['data']['data'][1]['email']);
    }

    public function test_create_returns_200_on_success(): void
    {
        $payload = ['email' => 'create.ok@example.com', 'name' => 'Valid Name'];

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $this->assertTrue(
            $this->client->getResponse()->headers->contains('Content-Type', 'application/json'),
            'Response is not JSON',
        );
        $content = $this->client->getResponse()->getContent();
        $this->assertNotSame('', $content, 'Empty response body');

        $json = \json_decode($content, true);
        $this->assertIsArray($json, 'Invalid JSON response: ' . $content);

        $this->assertSame('success', $json['status']);
        $this->assertSame('create.ok@example.com', $json['data']['email']);
        $this->assertSame('Valid Name', $json['data']['name']);

        /** @var UserRepositoryInterface $repo */
        $repo  = static::getContainer()->get(UserRepositoryInterface::class);
        $found = $repo->findByEmail(UserEmail::fromString('create.ok@example.com'));
        $this->assertInstanceOf(User::class, $found);
    }

    public function test_create_returns_422_on_missing_required_parameter(): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['name' => 'Test Name']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['email' => InvalidValueException::REQUIRED]);
    }

    public function test_create_returns_422_on_extra_parameter_not_allowed(): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => 'test@example.com', 'name' => 'Test Name', 'age' => 30]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['age' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_create_returns_422_on_invalid_parameter_type(): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => 'test@example.com', 'name' => 12345]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_create_returns_422_on_invalid_email_format(): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => 'invalid-email', 'name' => 'Valid Name']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['email' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_create_returns_422_on_name_too_short(): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => 'ok@example.com', 'name' => 'John']),
        );

        $problem = $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_SHORT]);
        $this->assertSame(['min' => UserName::MIN_LENGTH], $problem['errors'][0]['params']);
    }

    public function test_create_returns_422_on_name_too_long(): void
    {
        $long = \str_repeat('a', 256);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => 'ok@example.com', 'name' => $long]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_LONG]);
    }

    public function test_create_returns_409_when_already_exists(): void
    {
        /** @var UserRepositoryInterface $repo */
        $repo = static::getContainer()->get(UserRepositoryInterface::class);

        $email    = 'taken@example.com';
        $existing = new User(
            UserId::new(),
            UserEmail::fromString($email),
            UserName::fromString('Existing User'),
            new \DateTimeImmutable('2030-01-01T00:00:00+00:00'),
        );
        $repo->save($existing);

        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['email' => $email, 'name' => 'Anyone']),
        );

        $this->assertProblem($this->client, Response::HTTP_CONFLICT, UserAlreadyExistsException::ERROR_CODE, ['email' => UserAlreadyExistsException::ERROR_CODE]);
    }
}
