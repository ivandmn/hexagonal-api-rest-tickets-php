<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Symfony;

use App\Application\Event\EventBusInterface;
use App\Domain\Event\EventInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerEventBus implements EventBusInterface
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function dispatch(EventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->dispatchOne($event);
        }
    }

    private function dispatchOne(EventInterface $event): void
    {
        try {
            $this->eventBus->dispatch($event);
        } catch (HandlerFailedException $e) {
            $wrappedExceptions = $e->getWrappedExceptions();

            if (!empty($wrappedExceptions)) {
                throw \reset($wrappedExceptions);
            }

            throw $e;
        }
    }
}
