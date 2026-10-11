<?php

declare(strict_types=1);

namespace App\Application\Command;

interface CommandBusInterface
{
    /**
     * Returns whatever the handler returns: a view of the resource it created or changed, or null.
     *
     * @throws \Throwable the original exception thrown by the handler
     */
    public function dispatch(CommandInterface $command): mixed;
}
