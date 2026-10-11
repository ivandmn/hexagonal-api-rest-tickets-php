<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

use App\Domain\Exception\DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The only place that builds API error responses: Problem Details (RFC 9457) as
 * `application/problem+json`, with status and public message taken from the error mapping
 * (config/packages/error_mapping.yaml).
 *
 * Resolution order:
 *  1. validation failure (#[MapRequestPayload] / #[MapQueryString]) → 422 with one entry per violation;
 *  2. unknown payload fields → 422 with one entry per field;
 *  3. domain exception → status, message and field from the mapping (unmapped code = bug: 500 + warning);
 *  4. malformed request (bad JSON, empty or non-object body) → its request error code;
 *  5. any other HTTP exception from the framework (404, 405...) → its own status;
 *  6. anything else → generic 500 (details only with kernel.debug).
 *
 * Runs after Symfony's logger (priority 0), so exceptions are still logged once, and before its
 * HTML error page (priority -128). Domain errors are logged at "info" (framework.exceptions).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -64)]
final readonly class ApiExceptionListener
{
    private const CONTENT_TYPE = 'application/problem+json';

    public function __construct(
        private ErrorCodeMapper $mapper,
        private ConstraintViolationTranslator $violations,
        private LoggerInterface $logger,
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

        $exception  = $event->getThrowable();
        $validation = $this->validationFailure($exception);

        $payloadFailure = null !== $validation || $exception instanceof ExtraAttributesException;

        $problem = match (true) {
            // Valid JSON that is not an object ([1], "x", null) cannot be mapped to a request DTO.
            $payloadFailure && $this->isNonObjectJson($request) => $this->envelope(ApiErrorCode::INVALID_BODY),
            null !== $validation                                => $this->fromValidation($validation),
            $exception instanceof ExtraAttributesException      => $this->fromUnknownFields($exception),
            $exception instanceof DomainException               => $this->fromDomain($exception),
            default                                             => $this->fromRequest($exception, $request)
                ?? ($exception instanceof HttpExceptionInterface ? $this->fromHttp($exception) : $this->internal($exception)),
        };

        $traceId            = $request->headers->get('X-Request-Id') ?: \bin2hex(\random_bytes(8));
        $problem['traceId'] = $traceId;

        $response = new JsonResponse($problem, $problem['status'], ['Content-Type' => self::CONTENT_TYPE, 'X-Request-Id' => $traceId]);

        if ($exception instanceof HttpExceptionInterface) {
            $response->headers->add($exception->getHeaders());
        }

        $event->setResponse($response);
    }

    private function isNonObjectJson(Request $request): bool
    {
        $content = \trim($request->getContent());
        if ('' === $content || !\json_validate($content)) {
            return false;
        }

        $decoded = \json_decode($content, true);

        // "{}" decodes to an empty array, which is still an object.
        return !\is_array($decoded) || ([] !== $decoded && \array_is_list($decoded));
    }

    private function validationFailure(\Throwable $exception): ?ValidationFailedException
    {
        return match (true) {
            $exception instanceof ValidationFailedException                => $exception,
            $exception->getPrevious() instanceof ValidationFailedException => $exception->getPrevious(),
            default                                                        => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fromValidation(ValidationFailedException $exception): array
    {
        $errors = [];

        foreach ($exception->getViolations() as $violation) {
            ['code' => $code, 'params' => $params] = $this->violations->translate($violation);
            $mapping                               = $this->mapper->find($code);
            $path                                  = $violation->getPropertyPath();

            $errors[] = $this->fieldError(
                '' !== $path ? $path : $mapping?->field,
                $code,
                null !== $mapping ? $this->mapper->render($mapping, $params) : (string) $violation->getMessage(),
                $params,
            );
        }

        return $this->envelope(ApiErrorCode::VALIDATION_ERROR, $errors);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromUnknownFields(ExtraAttributesException $exception): array
    {
        $message = $this->requiredMapping(ApiErrorCode::UNKNOWN_FIELD)->message;
        $errors  = [];

        foreach ($exception->getExtraAttributes() as $field) {
            $errors[] = $this->fieldError((string) $field, ApiErrorCode::UNKNOWN_FIELD, $message, []);
        }

        return $this->envelope(ApiErrorCode::VALIDATION_ERROR, $errors);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDomain(DomainException $exception): array
    {
        $mapping = $this->mapper->find($exception->errorCode());

        if (null === $mapping) {
            $this->logger->warning('Unmapped domain error code "{errorCode}": add it to config/packages/error_mapping.yaml', [
                'errorCode' => $exception->errorCode(),
                'exception' => $exception,
            ]);

            return $this->internal($exception);
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
     * Problems with the request itself that the framework reports in a generic way.
     *
     * @return array<string, mixed>|null
     */
    private function fromRequest(\Throwable $exception, Request $request): ?array
    {
        $previous = $exception->getPrevious();

        $isPayloadError = $previous instanceof NotEncodableValueException
            || ($exception instanceof HttpExceptionInterface && Response::HTTP_UNPROCESSABLE_ENTITY === $exception->getStatusCode() && null === $previous);

        $code = match (true) {
            // No body at all: reported by the framework either as invalid JSON or as a bare 422.
            $isPayloadError && '' === \trim($request->getContent())  => ApiErrorCode::EMPTY_BODY,
            $previous instanceof NotEncodableValueException          => ApiErrorCode::MALFORMED_JSON,
            // The serializer could not map the body onto the request DTO.
            '' !== \trim($request->getContent())
                && ($exception instanceof SerializerException || $previous instanceof SerializerException) => ApiErrorCode::INVALID_BODY,
            default                                                                                        => null,
        };

        return null !== $code ? $this->envelope($code) : null;
    }

    /**
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, mixed>
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
     * @return array<string, mixed>
     */
    private function fieldError(?string $field, string $code, string $message, array $params): array
    {
        // An empty JSON object rather than [] keeps the shape stable for clients.
        return ['field' => $field, 'code' => $code, 'message' => $message, 'params' => [] !== $params ? $params : new \stdClass()];
    }
}
