<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Model\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsJsonResponse;
use App\Tests\Support\AssertsProblemResponse;
use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserControllerTest extends WebTestCase
{
    use AssertsJsonResponse;
    use AssertsProblemResponse;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    public function test_list_returns_200_with_an_empty_list(): void
    {
        $this->client->request('GET', '/api/users');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame([], $json['data']);
        $this->assertSame(['total' => 0], $json['meta']);
    }

    public function test_list_returns_every_user(): void
    {
        $this->givenUser('u1@example.com', 'User One');
        $this->givenUser('u2@example.com', 'User Two');

        $this->client->request('GET', '/api/users');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame(['total' => 2], $json['meta']);
        $this->assertEqualsCanonicalizing(['u1@example.com', 'u2@example.com'], \array_column($json['data'], 'email'));
        $this->assertSnakeCaseKeys($json);
    }

    public function test_get_returns_the_user(): void
    {
        $user = $this->givenUser('jane@example.com', 'Jane Doe');

        $this->client->request('GET', '/api/users/' . $user->getId()->value());

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame([
            'id'         => $user->getId()->value(),
            'email'      => 'jane@example.com',
            'name'       => 'Jane Doe',
            'created_at' => '2026-01-01T10:00:00+00:00',
            'updated_at' => null,
        ], $json['data']);
    }

    public function test_get_returns_404_for_an_unknown_user(): void
    {
        $userId = UserId::new()->value();

        $this->client->request('GET', '/api/users/' . $userId);

        $problem = $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, UserNotFoundException::ERROR_CODE);
        $this->assertSame(['user_id' => $userId], $problem['params']);
    }

    public function test_get_returns_404_for_an_id_that_is_not_a_uuid(): void
    {
        $this->client->request('GET', '/api/users/not-a-uuid');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_create_returns_201_with_the_user_and_its_location(): void
    {
        $this->postJson(['email' => 'Create.OK@example.com', 'name' => 'Valid Name']);

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_CREATED);
        $this->assertSame('create.ok@example.com', $json['data']['email']);
        $this->assertSame('Valid Name', $json['data']['name']);
        $this->assertNull($json['data']['updated_at']);
        $this->assertSnakeCaseKeys($json);

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertSame('http://localhost/api/users/' . $json['data']['id'], $location);

        // The Location points to a real resource.
        $this->client->request('GET', (string) $location);
        $this->assertSame($json['data'], $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data']);
    }

    public function test_create_returns_422_on_missing_required_parameter(): void
    {
        $this->postJson(['name' => 'Test Name']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['email' => InvalidValueException::REQUIRED]);
    }

    public function test_create_returns_422_on_extra_parameter_not_allowed(): void
    {
        $this->postJson(['email' => 'test@example.com', 'name' => 'Test Name', 'age' => 30]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['age' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_create_returns_422_on_invalid_parameter_type(): void
    {
        $this->postJson(['email' => 'test@example.com', 'name' => 12345]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_create_returns_422_on_invalid_email_format(): void
    {
        $this->postJson(['email' => 'invalid-email', 'name' => 'Valid Name']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['email' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_create_returns_422_on_name_too_short(): void
    {
        $this->postJson(['email' => 'ok@example.com', 'name' => 'John']);

        $problem = $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_SHORT]);
        $this->assertSame(['min' => UserName::MIN_LENGTH], $problem['errors'][0]['params']);
    }

    public function test_create_counts_characters_not_bytes_and_ignores_surrounding_spaces(): void
    {
        // 4 characters (8 bytes) is too short whatever the byte count; padding does not count either.
        $this->postJson(['email' => 'ok@example.com', 'name' => '  ÁÉÍÓ  ']);
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_SHORT]);

        $this->postJson(['email' => 'ok@example.com', 'name' => '  José Núñez  ']);
        $this->assertSame('José Núñez', $this->assertJsonSuccess($this->client, Response::HTTP_CREATED)['data']['name']);
    }

    public function test_create_returns_422_on_name_too_long(): void
    {
        $this->postJson(['email' => 'ok@example.com', 'name' => \str_repeat('a', 256)]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_LONG]);
    }

    public function test_create_returns_409_when_already_exists(): void
    {
        $this->givenUser('taken@example.com', 'Existing User');

        $this->postJson(['email' => 'taken@example.com', 'name' => 'Anyone']);

        $this->assertProblem($this->client, Response::HTTP_CONFLICT, UserAlreadyExistsException::ERROR_CODE, ['email' => UserAlreadyExistsException::ERROR_CODE]);
    }

    public function test_update_changes_the_given_fields(): void
    {
        $user = $this->givenUser('before@example.com', 'Before Name');

        $this->patchJson($user->getId()->value(), ['email' => 'After@Example.com', 'name' => 'After Name']);

        $data = $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data'];
        $this->assertSame($user->getId()->value(), $data['id']);
        $this->assertSame('after@example.com', $data['email']);
        $this->assertSame('After Name', $data['name']);
        $this->assertSame('2026-01-01T10:00:00+00:00', $data['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $data['updated_at']);
    }

    public function test_update_leaves_omitted_fields_as_they_are(): void
    {
        $user = $this->givenUser('keep@example.com', 'Old Name');

        $this->patchJson($user->getId()->value(), ['name' => 'New Name']);

        $data = $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data'];
        $this->assertSame('keep@example.com', $data['email']);
        $this->assertSame('New Name', $data['name']);
    }

    public function test_update_returns_409_when_the_email_belongs_to_another_user(): void
    {
        $this->givenUser('taken@example.com', 'Existing User');
        $user = $this->givenUser('mine@example.com', 'Some User');

        $this->patchJson($user->getId()->value(), ['email' => 'taken@example.com']);

        $this->assertProblem($this->client, Response::HTTP_CONFLICT, UserAlreadyExistsException::ERROR_CODE, ['email' => UserAlreadyExistsException::ERROR_CODE]);
    }

    public function test_update_returns_422_on_invalid_values_and_unknown_fields(): void
    {
        $user = $this->givenUser('valid@example.com', 'Valid Name');

        $this->patchJson($user->getId()->value(), ['email' => 'not-an-email']);
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['email' => InvalidValueException::INVALID_FORMAT]);

        $this->patchJson($user->getId()->value(), ['name' => 'ab']);
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['name' => InvalidValueException::TOO_SHORT]);

        $this->patchJson($user->getId()->value(), ['id' => 'x']);
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['id' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_update_returns_404_on_user_not_found(): void
    {
        $this->patchJson('018f63a8-bb93-7e5b-b0e1-49d24fa4ffff', ['name' => 'Some Name']);

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, UserNotFoundException::ERROR_CODE);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchJson(string $id, array $payload): void
    {
        $this->client->request(
            'PATCH',
            '/api/users/' . $id,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function givenUser(string $email, string $name): User
    {
        $user = User::create(UserId::new(), UserEmail::fromString($email), UserName::fromString($name), new \DateTimeImmutable('2026-01-01T10:00:00+00:00'));

        /** @var UserRepositoryInterface $repo */
        $repo = static::getContainer()->get(UserRepositoryInterface::class);
        $repo->save($user);

        return $user;
    }
}
