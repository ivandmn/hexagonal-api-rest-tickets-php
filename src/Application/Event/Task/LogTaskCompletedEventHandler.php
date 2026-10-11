<?php

declare(strict_types=1);

namespace App\Application\Event\Task;

use App\Application\Event\EventHandlerInterface;
use App\Domain\Event\Task\TaskCompleted;
use Psr\Log\LoggerInterface;

/**
 * Synchronous for now: runs inside the request that raised the event.
 */
final readonly class LogTaskCompletedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TaskCompleted $event): void
    {
        $this->logger->info('Task completed', [
            'event'       => $event->eventName(),
            'occurred_on' => (string) $event->occurredOn(),
            ...$event->payload(),
        ]);
    }
}
