<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Api;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\CommandInterface;
use App\Application\Query\QueryBusInterface;
use App\Application\Query\QueryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Base for the API controllers: they translate HTTP into commands and queries, and nothing else.
 */
abstract class ApiController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    protected function ask(QueryInterface $query): mixed
    {
        return $this->queryBus->dispatch($query);
    }

    protected function dispatch(CommandInterface $command): mixed
    {
        return $this->commandBus->dispatch($command);
    }
}
