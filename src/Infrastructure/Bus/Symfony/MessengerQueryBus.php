<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Symfony;

use App\Application\Query\QueryBusInterface;
use App\Application\Query\QueryInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBusInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    public function dispatch(QueryInterface $query): mixed
    {
        try {
            $envelope = $this->queryBus->dispatch($query);

            return $envelope->last(HandledStamp::class)?->getResult();
        } catch (HandlerFailedException $e) {
            $wrappedExceptions = $e->getWrappedExceptions();

            if (!empty($wrappedExceptions)) {
                throw \reset($wrappedExceptions);
            }

            throw $e;
        }
    }
}
