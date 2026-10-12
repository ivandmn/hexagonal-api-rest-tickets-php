<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Exception;

use App\Application\Validation\InvalidFieldException;
use App\Domain\Exception\DomainException;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\User\UserEmail;
use App\Tests\Support\SpyLogger;
use App\UI\Http\Exception\ApiErrorCode;
use App\UI\Http\Exception\ApiExceptionListener;
use App\UI\Http\Exception\ErrorCodeMapper;
use App\UI\Http\Request\InvalidPayloadException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Yaml\Yaml;

final class ApiExceptionListenerTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private static array $codes;

    private SpyLogger $appLogger;

    public static function setUpBeforeClass(): void
    {
        // The real mapping, so these tests also exercise the YAML.
        $config = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/packages/error_mapping.yaml');
        \assert(\is_array($config) && \is_array($config['parameters']) && \is_array($config['parameters']['app.error_mapping.codes']));

        self::$codes = $config['parameters']['app.error_mapping.codes'];
    }

    public function test_ignores_requests_outside_the_api(): void
    {
        $event = $this->dispatch(new \RuntimeException('boom'), path: '/info');

        $this->assertNull($event->getResponse());
    }

    public function test_domain_error_without_field_has_no_errors_list(): void
    {
        $taskId  = TaskId::new();
        $problem = $this->problem($this->dispatch(new TaskNotFoundException($taskId)), Response::HTTP_NOT_FOUND);

        $this->assertSame('TASK_NOT_FOUND', $problem['type']);
        $this->assertSame(\sprintf('Task %s does not exist.', $taskId->value()), $problem['title']);
        $this->assertSame(['task_id' => $taskId->value()], $problem['params']);
        $this->assertArrayNotHasKey('errors', $problem);
    }

    public function test_domain_error_with_field_in_the_mapping_also_lists_it_in_errors(): void
    {
        $problem = $this->problem($this->dispatch(new UserAlreadyExistsException(UserEmail::fromString('jane@example.com'))), Response::HTTP_CONFLICT);

        $this->assertSame([[
            'field'   => 'email',
            'code'    => 'USER_ALREADY_EXISTS',
            'message' => 'A user with email jane@example.com already exists.',
            'params'  => ['email' => 'jane@example.com'],
        ]], $problem['errors']);
    }

    public function test_unmapped_domain_code_is_a_500_and_logs_a_warning(): void
    {
        $exception = new class extends DomainException {
            public function __construct()
            {
                parent::__construct('NOT_IN_THE_MAPPING', 'internal detail');
            }
        };

        $problem = $this->problem($this->dispatch($exception), Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->assertSame(ApiErrorCode::INTERNAL_ERROR, $problem['type']);
        $this->assertSame('warning', $this->appLogger->records[0]['level'] ?? null);
        $this->assertSame('NOT_IN_THE_MAPPING', $this->appLogger->records[0]['context']['errorCode']);
    }

    public function test_unexpected_errors_hide_details_without_debug(): void
    {
        $problem = $this->problem($this->dispatch(new \RuntimeException('SQLSTATE secret detail')), Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->assertSame(['type' => 'INTERNAL_ERROR', 'title' => 'An unexpected error occurred.', 'status' => 500], \array_diff_key($problem, ['trace_id' => 1]));
        $this->assertStringNotContainsString('secret', (string) \json_encode($problem));
    }

    public function test_unexpected_errors_show_details_in_debug(): void
    {
        $problem = $this->problem($this->dispatch(new \RuntimeException('boom'), debug: true), Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->assertSame(\RuntimeException::class, $problem['debug']['exception']);
        $this->assertSame('boom', $problem['debug']['message']);
    }

    public function test_rejected_command_input_is_a_validation_error_on_that_field(): void
    {
        $problem = $this->problem($this->dispatch(new InvalidFieldException('title', InvalidValueException::tooShort(5))), Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(ApiErrorCode::VALIDATION_ERROR, $problem['type']);
        $this->assertSame([
            ['field' => 'title', 'code' => 'VALUE_TOO_SHORT', 'message' => 'This value must be at least 5 characters long.', 'params' => ['min' => 5]],
        ], $problem['errors']);
    }

    public function test_command_input_names_become_payload_names_in_snake_case(): void
    {
        $problem = $this->problem($this->dispatch(new InvalidFieldException('dueDate', InvalidValueException::required())), Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([['field' => 'due_date', 'code' => 'VALUE_REQUIRED', 'message' => 'This value is required.', 'params' => []]], $problem['errors']);
    }

    public function test_unknown_fields_are_validation_errors(): void
    {
        $problem = $this->problem($this->dispatch(InvalidPayloadException::unknownFields(['foo', 'bar'])), Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame(ApiErrorCode::VALIDATION_ERROR, $problem['type']);
        $this->assertSame(['foo', 'bar'], \array_column($problem['errors'], 'field'));
        $this->assertSame([ApiErrorCode::UNKNOWN_FIELD, ApiErrorCode::UNKNOWN_FIELD], \array_column($problem['errors'], 'code'));
    }

    public function test_wrong_types_are_validation_errors_with_the_expected_type(): void
    {
        $problem = $this->problem($this->dispatch(InvalidPayloadException::invalidType('title', 'string')), Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([
            ['field' => 'title', 'code' => ApiErrorCode::INVALID_TYPE, 'message' => 'This value should be of type string.', 'params' => ['expected' => 'string']],
        ], $problem['errors']);
    }

    public function test_body_level_payload_errors_have_no_errors_list(): void
    {
        $problem = $this->problem($this->dispatch(InvalidPayloadException::malformedJson()), Response::HTTP_BAD_REQUEST);

        $this->assertSame(ApiErrorCode::MALFORMED_JSON, $problem['type']);
        $this->assertArrayNotHasKey('errors', $problem);
    }

    public function test_framework_http_errors_keep_their_status_and_headers(): void
    {
        $event = $this->dispatch(new MethodNotAllowedHttpException(['GET', 'PUT'], 'Method Not Allowed'));

        $problem = $this->problem($event, Response::HTTP_METHOD_NOT_ALLOWED);
        $this->assertSame('METHOD_NOT_ALLOWED', $problem['type']);
        $this->assertSame('GET, PUT', $event->getResponse()?->headers->get('Allow'));
    }

    public function test_trace_id_reuses_the_request_id_header(): void
    {
        $event = $this->dispatch(new \RuntimeException('boom'), requestId: 'req-123');

        $this->assertSame('req-123', $this->problem($event, 500)['trace_id']);
        $this->assertSame('req-123', $event->getResponse()?->headers->get('X-Request-Id'));
    }

    private function dispatch(\Throwable $exception, string $path = '/api/v1/tasks', bool $debug = false, ?string $requestId = null): ExceptionEvent
    {
        $this->appLogger = new SpyLogger();

        $request = Request::create($path);
        if (null !== $requestId) {
            $request->headers->set('X-Request-Id', $requestId);
        }

        $event    = new ExceptionEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $listener = new ApiExceptionListener(new ErrorCodeMapper(self::$codes), $this->appLogger, new CamelCaseToSnakeCaseNameConverter(), $debug, '/api');
        $listener($event);

        return $event;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function problem(ExceptionEvent $event, int $status): array
    {
        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $problem = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertIsArray($problem);
        $this->assertSame($status, $problem['status']);
        $this->assertNotEmpty($problem['trace_id']);

        return $problem;
    }
}
