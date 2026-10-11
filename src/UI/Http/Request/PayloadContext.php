<?php

declare(strict_types=1);

namespace App\UI\Http\Request;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Serialization context for #[MapRequestPayload] / #[MapQueryString]: unknown fields are
 * rejected (REQUEST_UNKNOWN_FIELD) instead of silently ignored.
 */
final class PayloadContext
{
    public const STRICT = [AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false];

    private function __construct()
    {
    }
}
