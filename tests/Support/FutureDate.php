<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Deterministic dates that are always in the future (TaskDueDate rejects past ones), so tests
 * can still assert exact serialized values without breaking once a hard-coded year goes by.
 */
final class FutureDate
{
    public static function on(int $month = 1, int $day = 1, string $time = '00:00:00'): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\sprintf('%d-%02d-%02dT%s+00:00', (int) \date('Y') + 1, $month, $day, $time));
    }
}
