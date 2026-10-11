<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Bus\Symfony;

use App\Domain\Event\EventInterface;
use App\Domain\ValueObject\DateTime;
use App\Infrastructure\Bus\Symfony\MessengerEventBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerEventBusTest extends TestCase
{
    private EventInterface $event;

    protected function setUp(): void
    {
        $this->event = new class implements EventInterface {
            public function eventName(): string
            {
                return 'test.happened';
            }

            public function occurredOn(): DateTime
            {
                return DateTime::fromTimestamp(0);
            }

            public function payload(): array
            {
                return [];
            }
        };
    }

    public function test_every_handler_receives_the_event(): void
    {
        $event    = $this->event;
        $received = [];

        $bus = $this->bus(
            static function (EventInterface $e) use (&$received): void {
                $received[] = ['first', $e];
            },
            static function (EventInterface $e) use (&$received): void {
                $received[] = ['second', $e];
            },
        );

        $bus->dispatch($event);

        $this->assertSame([['first', $event], ['second', $event]], $received);
    }

    public function test_dispatches_several_events_in_order(): void
    {
        $other    = clone $this->event;
        $received = [];

        $bus = new MessengerEventBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([$this->event::class => [static function (EventInterface $e) use (&$received): void {
                $received[] = $e;
            }]])),
        ]));

        $bus->dispatch($this->event, $other);
        $bus->dispatch();

        $this->assertCount(2, $received);
        $this->assertSame($this->event, $received[0]);
        $this->assertSame($other, $received[1]);
    }

    public function test_an_event_nobody_listens_to_is_fine(): void
    {
        $bus = new MessengerEventBus(new MessageBus([new HandleMessageMiddleware(new HandlersLocator([]), allowNoHandlers: true)]));

        $bus->dispatch($this->event);

        $this->addToAssertionCount(1);
    }

    public function test_rethrows_the_first_handler_exception_unwrapped(): void
    {
        $first = new \RuntimeException('first');
        $bus   = $this->bus(static fn () => throw $first, static fn () => throw new \LogicException('second'));

        try {
            $bus->dispatch($this->event);
            $this->fail('The handler exception should have been rethrown.');
        } catch (\RuntimeException $thrown) {
            $this->assertSame($first, $thrown);
        }
    }

    private function bus(callable ...$handlers): MessengerEventBus
    {
        // Every closure is named "Closure::__invoke": Messenger needs an alias to tell them apart.
        $descriptors = [];
        foreach (\array_values($handlers) as $i => $handler) {
            $descriptors[] = new HandlerDescriptor($handler(...), ['alias' => 'handler' . $i]);
        }

        return new MessengerEventBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([$this->event::class => $descriptors])),
        ]));
    }
}
