<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Records log calls so tests can assert on them.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : \get_debug_type($level), 'message' => (string) $message, 'context' => $context];
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
