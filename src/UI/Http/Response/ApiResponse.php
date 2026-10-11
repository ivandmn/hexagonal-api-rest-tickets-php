<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Success envelope of the API: {"status": "success", "data": ...}.
 * Error responses are built only by App\UI\Http\Exception\ApiExceptionListener.
 */
final class ApiResponse
{
    public const STATUS_SUCCESS = 'success';

    public static function success(mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = ['status' => self::STATUS_SUCCESS];

        if (null !== $data) {
            $payload['data'] = $data;
        }

        return new JsonResponse($payload, $status);
    }

    public static function empty(): JsonResponse
    {
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
