<?php

declare(strict_types=1);

namespace App\Tests\Support\Double;

use App\Application\Event\EventBusInterface;
use App\Domain\Event\EventInterface;

/**
 * Test double: records the dispatched events instead of handling them.
 */
final class SpyEventBus implements EventBusInterface
{
    /** @var list<EventInterface> */
    public array $events = [];

    public function dispatch(EventInterface ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /**
     * @template T of EventInterface
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function ofType(string $class): array
    {
        return \array_values(\array_filter($this->events, static fn (EventInterface $event): bool => $event instanceof $class));
    }
}
