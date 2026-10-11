<?php

declare(strict_types=1);

namespace App\Infrastructure\Symfony\Controller;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\CommandInterface;
use App\Application\Query\QueryBusInterface;
use App\Application\Query\QueryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function queryHandler(QueryInterface $query): mixed
    {
        return $this->queryBus->dispatch($query);
    }

    public function commandHandler(CommandInterface $command): mixed
    {
        return $this->commandBus->dispatch($command);
    }
}
