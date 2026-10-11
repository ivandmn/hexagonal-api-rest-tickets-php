<?php

declare(strict_types=1);

namespace App\Tests\Support\Double;

use App\Application\Service\LoggerInterface;

/**
 * Records what the application layer logs (its own logging port, not PSR-3).
 */
final class SpyAppLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    public function info(string $message, array $context = []): void
    {
        $this->records[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function error(string $message, array $context = []): void
    {
        $this->records[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    /**
     * @param array<array-key, mixed> $context expected subset of the logged context
     */
    public function has(string $level, string $message, array $context = []): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message && \array_intersect_key($record['context'], $context) == $context) {
                return true;
            }
        }

        return false;
    }
}
