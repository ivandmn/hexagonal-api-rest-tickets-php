<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Symfony;

use App\Application\Command\CommandBusInterface;
use App\Application\Command\CommandInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(CommandInterface $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);

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
