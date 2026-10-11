<?php

declare(strict_types=1);

namespace App\Application\Event\User;

use App\Application\Event\EventHandlerInterface;
use App\Domain\Event\User\UserDeleted;
use Psr\Log\LoggerInterface;

/**
 * Synchronous for now: runs inside the request that raised the event.
 */
final readonly class LogUserDeletedEventHandler implements EventHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(UserDeleted $event): void
    {
        $this->logger->info('User deleted', [
            'event'       => $event->eventName(),
            'occurred_on' => (string) $event->occurredOn(),
            ...$event->payload(),
        ]);
    }
}
