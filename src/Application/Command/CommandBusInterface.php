<?php

declare(strict_types=1);

namespace App\Application\Command;

interface CommandBusInterface
{
    /**
     * @throws \Throwable
     */
    public function dispatch(CommandInterface $command): mixed;
}
