<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Bus\Symfony;

use App\Application\Command\CommandInterface;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Infrastructure\Bus\Symfony\MessengerCommandBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;

final class MessengerCommandBusTest extends TestCase
{
    private CommandInterface $command;

    protected function setUp(): void
    {
        $this->command = new class implements CommandInterface {
        };
    }

    public function test_returns_what_the_handler_returns(): void
    {
        $bus = $this->bus(static fn (): string => 'the view');

        $this->assertSame('the view', $bus->dispatch($this->command));
    }

    public function test_returns_null_when_the_handler_returns_nothing(): void
    {
        $bus = $this->bus(static function (): void {
        });

        $this->assertNull($bus->dispatch($this->command));
    }

    public function test_rethrows_the_handler_exception_unwrapped(): void
    {
        $exception = new TaskNotFoundException(TaskId::new());
        $bus       = $this->bus(static fn () => throw $exception);

        try {
            $bus->dispatch($this->command);
            $this->fail('The handler exception should have been rethrown.');
        } catch (TaskNotFoundException $thrown) {
            // Same instance: the listener maps it as the domain error, not as Messenger's wrapper.
            $this->assertSame($exception, $thrown);
        }
    }

    public function test_a_command_without_handler_is_a_bug_and_is_not_swallowed(): void
    {
        $bus = new MessengerCommandBus(new MessageBus([new HandleMessageMiddleware(new HandlersLocator([]))]));

        $this->expectException(NoHandlerForMessageException::class);
        $bus->dispatch($this->command);
    }

    private function bus(callable $handler): MessengerCommandBus
    {
        return new MessengerCommandBus(new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([$this->command::class => [$handler]])),
        ]));
    }
}
