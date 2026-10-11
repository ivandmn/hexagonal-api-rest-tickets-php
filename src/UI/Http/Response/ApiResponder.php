<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Builds the success responses of the API from Application views: this is where the JSON format
 * lives (snake_case keys through the serializer's name converter, RFC 3339 dates).
 *
 *   single resource  {"status": "success", "data": {...}}
 *   collection       {"status": "success", "data": [...], "meta": {...}}
 *
 * Error responses are built only by App\UI\Http\Exception\ApiExceptionListener.
 */
final readonly class ApiResponder
{
    public const STATUS_SUCCESS = 'success';

    private const CONTEXT = [DateTimeNormalizer::FORMAT_KEY => \DATE_ATOM];

    public function __construct(
        private NormalizerInterface $normalizer,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function ok(object $resource): JsonResponse
    {
        return $this->json(['status' => self::STATUS_SUCCESS, 'data' => $this->normalize($resource)]);
    }

    /**
     * 201 with a Location header pointing to the new resource.
     *
     * @param array<string, string> $parameters
     */
    public function created(object $resource, string $route, array $parameters): JsonResponse
    {
        return $this->json(
            ['status' => self::STATUS_SUCCESS, 'data' => $this->normalize($resource)],
            Response::HTTP_CREATED,
            ['Location' => $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL)],
        );
    }

    /**
     * @param list<object>         $items
     * @param array<string, mixed> $meta
     */
    public function collection(array $items, array $meta): JsonResponse
    {
        return $this->json([
            'status' => self::STATUS_SUCCESS,
            'data'   => \array_map($this->normalize(...), $items),
            'meta'   => $this->normalize($meta),
        ]);
    }

    public function noContent(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function normalize(mixed $data): mixed
    {
        return $this->normalizer->normalize($data, 'json', self::CONTEXT);
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    private function json(array $payload, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($payload, $status, $headers);
    }
}
