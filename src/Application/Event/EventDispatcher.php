<?php

declare(strict_types=1);

namespace App\Application\Event;

use App\Domain\Event\EventInterface;
use App\Domain\Model\DomainEntity;

final readonly class EventDispatcher
{
    public function __construct(
        private EventBusInterface $eventBus,
    ) {
    }

    public function dispatchEntityEvents(DomainEntity $entity): void
    {
        if ($entity->hasPendingEvents()) {
            foreach ($entity->pullEvents() as $event) {
                $this->eventBus->dispatch($event);
            }
        }
    }

    public function dispatch(EventInterface $event): void
    {
        $this->eventBus->dispatch($event);
    }
}
