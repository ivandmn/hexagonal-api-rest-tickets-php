<?php

declare(strict_types=1);

namespace App\Application\Query;

interface QueryBusInterface
{
    /**
     * @throws \Throwable
     */
    public function dispatch(QueryInterface $query): mixed;
}
