<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\InvalidValueException;
use App\Tests\Support\AssertsProblemResponse;
use App\UI\Http\Exception\ApiErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end checks of the error contract for problems with the request itself.
 */
final class ApiErrorHandlingTest extends WebTestCase
{
    use AssertsProblemResponse;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'malformed JSON' => ['{"title": ', Response::HTTP_BAD_REQUEST, ApiErrorCode::MALFORMED_JSON];
        yield 'empty body' => ['', Response::HTTP_BAD_REQUEST, ApiErrorCode::EMPTY_BODY];
        yield 'JSON list' => ['[1, 2]', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_BODY];
        yield 'JSON string' => ['"text"', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_BODY];
        yield 'JSON null' => ['null', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_BODY];
    }

    #[DataProvider('malformedBodies')]
    public function test_malformed_bodies(string $body, int $status, string $type): void
    {
        $this->client->request('POST', '/api/users', server: ['CONTENT_TYPE' => 'application/json'], content: $body);

        $problem = $this->assertProblem($this->client, $status, $type);
        $this->assertArrayNotHasKey('errors', $problem);
    }

    public function test_reports_every_invalid_field_at_once(): void
    {
        $this->client->request('POST', '/api/tasks', server: ['CONTENT_TYPE' => 'application/json'], content: \json_encode([
            'title'       => 'abc',
            'priority'    => 'urgent',
            'dueDate'     => 'tomorrow',
            'unknown'     => true,
        ], \JSON_THROW_ON_ERROR));

        // Unknown fields are rejected before validation runs.
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['unknown' => ApiErrorCode::UNKNOWN_FIELD]);

        $this->client->request('POST', '/api/tasks', server: ['CONTENT_TYPE' => 'application/json'], content: \json_encode([
            'title'    => 'abc',
            'priority' => 'urgent',
            'dueDate'  => 'tomorrow',
        ], \JSON_THROW_ON_ERROR));

        $problem = $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, [
            'title'       => InvalidValueException::TOO_SHORT,
            'description' => InvalidValueException::REQUIRED,
            'priority'    => InvalidValueException::INVALID_CHOICE,
            'dueDate'     => InvalidValueException::INVALID_FORMAT,
        ]);

        // Public messages come from the mapping, with placeholders resolved.
        $this->assertSame('This value must be at least 5 characters long.', $problem['errors'][0]['message']);
        $this->assertSame('This value must be one of: low, medium, high.', $problem['errors'][2]['message']);
    }

    public function test_query_string_errors(): void
    {
        $this->client->request('GET', '/api/tasks?page=0&limit=abc&status=done');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['limit' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_only_json_is_accepted(): void
    {
        $this->client->request('POST', '/api/users', server: ['CONTENT_TYPE' => 'text/plain'], content: 'email=x');

        $this->assertProblem($this->client, Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'UNSUPPORTED_MEDIA_TYPE');
    }

    public function test_unknown_api_routes_and_methods(): void
    {
        $this->client->request('GET', '/api/does-not-exist');
        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');

        $this->client->request('POST', '/api/tasks/3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63');
        $this->assertProblem($this->client, Response::HTTP_METHOD_NOT_ALLOWED, 'METHOD_NOT_ALLOWED');
        $this->assertNotNull($this->client->getResponse()->headers->get('Allow'));
    }

    public function test_request_id_is_echoed_as_trace_id(): void
    {
        $this->client->request('GET', '/api/does-not-exist', server: ['HTTP_X_REQUEST_ID' => 'trace-me-42']);

        $this->assertSame('trace-me-42', $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND')['traceId']);
    }

    public function test_routes_outside_the_api_keep_symfony_errors(): void
    {
        $this->client->request('GET', '/not-an-api-route');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertNotSame('application/problem+json', $this->client->getResponse()->headers->get('Content-Type'));
    }
}
