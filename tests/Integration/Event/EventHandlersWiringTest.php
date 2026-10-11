<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Application\Event\Task\LogTaskAssignedEventHandler;
use App\Application\Event\Task\LogTaskCompletedEventHandler;
use App\Application\Event\User\LogUserDeletedEventHandler;
use App\Domain\Event\Task\TaskAssigned;
use App\Domain\Event\Task\TaskCompleted;
use App\Domain\Event\User\UserDeleted;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The event bus allows events without handlers, so a handler that is not registered would fail
 * silently: check the container really routes each event to its handler.
 */
final class EventHandlersWiringTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function handlers(): iterable
    {
        yield 'task assigned' => [TaskAssigned::class, LogTaskAssignedEventHandler::class];
        yield 'task completed' => [TaskCompleted::class, LogTaskCompletedEventHandler::class];
        yield 'user deleted' => [UserDeleted::class, LogUserDeletedEventHandler::class];
    }

    #[DataProvider('handlers')]
    public function test_the_event_bus_routes_the_event_to_its_handler(string $event, string $handler): void
    {
        $tester = new CommandTester(new Application(self::bootKernel())->find('debug:messenger'));
        $tester->execute(['bus' => 'event.bus']);

        $this->assertMatchesRegularExpression(
            \sprintf('/%s\s+handled by %s \(when bus=event\.bus\)/', \preg_quote($event, '/'), \preg_quote($handler, '/')),
            $tester->getDisplay(),
        );
    }
}
