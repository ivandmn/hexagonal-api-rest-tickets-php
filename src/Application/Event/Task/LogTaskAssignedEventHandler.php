<?php

declare(strict_types=1);

namespace App\Application\Event\Task;

use App\Application\Event\EventHandlerInterface;
use App\Domain\Event\Task\TaskAssigned;
use Psr\Log\LoggerInterface;

/**
 * Synchronous for now: runs inside the request that raised the event.
 */
final readonly class LogTaskAssignedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private LoggerInterface $appLogger,
    ) {
    }

    public function __invoke(TaskAssigned $event): void
    {
        $this->appLogger->info('Task assigned to user', [
            'event'       => $event->eventName(),
            'occurred_on' => (string) $event->occurredOn(),
            ...$event->payload(),
        ]);
    }
}
