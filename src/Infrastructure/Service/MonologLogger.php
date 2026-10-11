<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Service\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLogger;

final readonly class MonologLogger implements LoggerInterface
{
    public function __construct(
        private PsrLogger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
