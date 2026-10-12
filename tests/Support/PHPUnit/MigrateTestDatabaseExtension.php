<?php

declare(strict_types=1);

namespace App\Tests\Support\PHPUnit;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Creates the test database if needed and brings it up to date before any integration or
 * functional test runs, so `bin/phpunit` (CLI or IDE) works without `make test-db` first.
 * Unit-only runs never touch it.
 *
 * The commands run in a separate process: DAMA keeps static connections inside this one, and the
 * schema changes must be committed, not rolled back with the first test.
 */
final class MigrateTestDatabaseExtension implements Extension, ExecutionStartedSubscriber
{
    private const array SUITES_WITH_DATABASE = ['App\Tests\Integration\\', 'App\Tests\Functional\\'];

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber($this);
    }

    public function notify(ExecutionStarted $event): void
    {
        if (!$this->needsDatabase($event)) {
            return;
        }

        $this->console('doctrine:database:create', '--if-not-exists', '-q');
        // The migrations table lives in the "tickets" schema, so it has to exist before migrating.
        $this->console('dbal:run-sql', '-q', 'CREATE SCHEMA IF NOT EXISTS tickets');
        $this->console('doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration', '-q');
    }

    private function needsDatabase(ExecutionStarted $event): bool
    {
        foreach ($event->testSuite()->tests() as $test) {
            if (!$test instanceof TestMethod) {
                continue;
            }

            foreach (self::SUITES_WITH_DATABASE as $namespace) {
                if (\str_starts_with($test->className(), $namespace)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function console(string ...$arguments): void
    {
        $command = [\PHP_BINARY, \dirname(__DIR__, 3) . '/bin/console', '--env=test', ...$arguments];

        \exec(\implode(' ', \array_map(escapeshellarg(...), $command)) . ' 2>&1', $output, $exitCode);

        if (0 !== $exitCode) {
            throw new \RuntimeException(\sprintf("Could not prepare the test database (%s):\n%s", $arguments[0], \implode("\n", $output)));
        }
    }
}
