<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

use App\Application\Validation\InvalidFieldException;
use App\Domain\Exception\DomainException;
use App\UI\Http\Request\InvalidPayloadException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * The only place that builds API error responses: Problem Details (RFC 9457) as
 * `application/problem+json`, with status and public message taken from the error mapping
 * (config/packages/error_mapping.yaml).
 *
 * Resolution order:
 *  1. request input with the wrong shape (App\UI\Http\Request\Payload): bad JSON, empty or
 *     non-object body → its request error code; unknown fields or wrong types → 422 with one entry per field;
 *  2. command/query input rejected by the domain (App\Application\Validation\Field) → 422 with
 *     that field and the domain error code;
 *  3. domain exception → status, message and field from the mapping (unmapped code = bug: 500 + warning);
 *  4. any other HTTP exception from the framework (404, 405, 415...) → its own status;
 *  5. anything else → generic 500 (details only with kernel.debug).
 *
 * Runs after Symfony's logger (priority 0), so exceptions are still logged once, and before its
 * HTML error page (priority -128). Domain errors are logged at "info" (framework.exceptions).
 *
 * @phpstan-type FieldError array{field: string|null, code: string, message: string, params: array<string, scalar|null>|\stdClass}
 * @phpstan-type Problem array{type: string, title: string, status: int, params?: array<string, scalar|null>, errors?: list<FieldError>, debug?: array{exception: class-string, message: string, at: string}}
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ApiExceptionListener
{
    private const CONTENT_TYPE = 'application/problem+json';

    public function __construct(
        private ErrorCodeMapper $mapper,
        private LoggerInterface $appLogger,
        // Same converter as the serializer, so errors[].field matches the payload keys (snake_case).
        #[Autowire(service: 'serializer.name_converter.camel_case_to_snake_case')]
        private NameConverterInterface $nameConverter,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        #[Autowire(param: 'app.error_mapping.path_prefix')]
        private string $pathPrefix,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!\str_starts_with($request->getPathInfo(), $this->pathPrefix)) {
            return;
        }

        $exception = $event->getThrowable();

        $problem = match (true) {
            $exception instanceof InvalidPayloadException => $this->fromPayload($exception),
            $exception instanceof InvalidFieldException   => $this->fromField($exception),
            $exception instanceof DomainException         => $this->fromDomain($exception),
            $exception instanceof HttpExceptionInterface  => $this->fromHttp($exception),
            default                                       => $this->internal($exception),
        };

        $traceId             = $request->headers->get('X-Request-Id') ?: \bin2hex(\random_bytes(8));
        $problem['trace_id'] = $traceId;

        $response = new JsonResponse($problem, $problem['status'], ['Content-Type' => self::CONTENT_TYPE, 'X-Request-Id' => $traceId]);

        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }

        $event->setResponse($response);
    }

    /**
     * @return Problem
     */
    private function fromPayload(InvalidPayloadException $exception): array
    {
        if ([] === $exception->fields) {
            return $this->envelope($exception->errorCode);
        }

        $message = $this->mapper->render($this->requiredMapping($exception->errorCode), $exception->params);
        $errors  = [];

        foreach ($exception->fields as $field) {
            $errors[] = $this->fieldError($field, $exception->errorCode, $message, $exception->params);
        }

        return $this->envelope(ApiErrorCode::VALIDATION_ERROR, $errors);
    }

    /**
     * @return Problem
     */
    private function fromField(InvalidFieldException $exception): array
    {
        $error   = $exception->error;
        $mapping = $this->mapper->find($error->errorCode());

        if (null === $mapping) {
            return $this->unmapped($error);
        }

        return $this->envelope(ApiErrorCode::VALIDATION_ERROR, [
            $this->fieldError($this->fieldName($exception->field), $error->errorCode(), $this->mapper->render($mapping, $error->params()), $error->params()),
        ]);
    }

    /**
     * @return Problem
     */
    private function fromDomain(DomainException $exception): array
    {
        $mapping = $this->mapper->find($exception->errorCode());

        if (null === $mapping) {
            return $this->unmapped($exception);
        }

        $message = $this->mapper->render($mapping, $exception->params());
        $problem = ['type' => $exception->errorCode(), 'title' => $message, 'status' => $mapping->status];

        if ([] !== $exception->params()) {
            $problem['params'] = $exception->params();
        }

        // A field in the mapping lets clients handle it exactly like a validation error.
        if (null !== $mapping->field) {
            $problem['errors'] = [$this->fieldError($mapping->field, $exception->errorCode(), $message, $exception->params())];
        }

        return $problem;
    }

    /**
     * @return Problem
     */
    private function fromHttp(HttpExceptionInterface $exception): array
    {
        $status = $exception->getStatusCode();
        $text   = Response::$statusTexts[$status] ?? 'Error';

        return [
            'type'   => \strtoupper(\str_replace([' ', '-'], '_', $text)),
            'title'  => $status < 500 && '' !== $exception->getMessage() ? $exception->getMessage() : $text,
            'status' => $status,
        ];
    }

    /**
     * An unmapped code is a bug: answer 500 and warn.
     *
     * @return Problem
     */
    private function unmapped(DomainException $exception): array
    {
        $this->appLogger->warning('Unmapped domain error code "{errorCode}": add it to config/packages/error_mapping.yaml', [
            'errorCode' => $exception->errorCode(),
            'exception' => $exception,
        ]);

        return $this->internal($exception);
    }

    /**
     * @return Problem
     */
    private function internal(\Throwable $exception): array
    {
        $problem = $this->envelope(ApiErrorCode::INTERNAL_ERROR);

        if ($this->debug) {
            $problem['debug'] = [
                'exception' => $exception::class,
                'message'   => $exception->getMessage(),
                'at'        => $exception->getFile() . ':' . $exception->getLine(),
            ];
        }

        return $problem;
    }

    /**
     * Problem for one of the API's own codes; status and title come from the mapping too.
     *
     * @param list<FieldError> $errors
     *
     * @return Problem
     */
    private function envelope(string $code, array $errors = []): array
    {
        $mapping = $this->requiredMapping($code);
        $problem = ['type' => $code, 'title' => $mapping->message, 'status' => $mapping->status];

        if ([] !== $errors) {
            $problem['errors'] = $errors;
        }

        return $problem;
    }

    /**
     * "dueDate" → "due_date": command inputs use PHP names, the payload uses snake_case.
     */
    private function fieldName(string $propertyPath): string
    {
        return (string) \preg_replace_callback(
            '/[A-Za-z_][A-Za-z0-9_]*/',
            fn (array $match): string => $this->nameConverter->normalize($match[0]),
            $propertyPath,
        );
    }

    /**
     * The API's own codes must always be mapped (ErrorCodeCoverageTest guarantees it).
     */
    private function requiredMapping(string $code): ErrorMapping
    {
        return $this->mapper->find($code)
            ?? throw new \LogicException(\sprintf('Error code "%s" must be mapped in config/packages/error_mapping.yaml.', $code));
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return FieldError
     */
    private function fieldError(?string $field, string $code, string $message, array $params): array
    {
        // An empty JSON object rather than [] keeps the shape stable for clients.
        return ['field' => $field, 'code' => $code, 'message' => $message, 'params' => [] !== $params ? $params : new \stdClass()];
    }
}
