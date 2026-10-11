<?php

declare(strict_types=1);

namespace App\Application\Event;

use App\Domain\Event\EventInterface;

interface EventBusInterface
{
    /**
     * In order; usually what an aggregate recorded: `dispatch(...$task->pullDomainEvents())`.
     */
    public function dispatch(EventInterface ...$events): void;
}
