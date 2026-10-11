<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Event\EventInterface;

/**
 * Records the domain events raised by the aggregate's own operations. The handler that persists
 * the aggregate pulls and dispatches them afterwards, so nothing is announced that was not saved.
 */
abstract class AggregateRoot
{
    /** @var list<EventInterface> not mapped: Doctrine leaves it at its default when loading */
    private array $domainEvents = [];

    /**
     * Returns the events recorded so far and forgets them, so each one is dispatched only once.
     *
     * @return list<EventInterface>
     */
    final public function pullDomainEvents(): array
    {
        $events             = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    final protected function record(EventInterface $event): void
    {
        $this->domainEvents[] = $event;
    }
}
