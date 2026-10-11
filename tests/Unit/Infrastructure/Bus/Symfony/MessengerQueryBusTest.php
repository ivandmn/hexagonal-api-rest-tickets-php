<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Bus\Symfony;

use App\Application\Query\QueryInterface;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\User\UserId;
use App\Infrastructure\Bus\Symfony\MessengerQueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerQueryBusTest extends TestCase
{
    private QueryInterface $query;

    protected function setUp(): void
    {
        $this->query = new class implements QueryInterface {
        };
    }

    public function test_returns_what_the_handler_returns(): void
    {
        $result = new \stdClass();
        $bus    = $this->bus(static fn (): \stdClass => $result);

        $this->assertSame($result, $bus->dispatch($this->query));
    }

    public function test_rethrows_the_handler_exception_unwrapped(): void
    {
        $exception = new UserNotFoundException(UserId::new());
        $bus       = $this->bus(static fn () => throw $exception);

        try {
            $bus->dispatch($this->query);
            $this->fail('The handler exception should have been rethrown.');
        } catch (UserNotFoundException $thrown) {
            $this->assertSame($exception, $thrown);
        }
    }

    public function test_a_query_without_handler_is_a_bug_and_is_not_swallowed(): void
    {
        $bus = new MessengerQueryBus(new MessageBus([new HandleMessageMiddleware(new HandlersLocator([]))]));

        $this->expectException(NoHandlerForMessageException::class);
        $bus->dispatch($this->query);
    }

    private function bus(callable $handler): MessengerQueryBus
    {
        return new MessengerQueryBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([$this->query::class => [$handler]])),
        ]));
    }
}
