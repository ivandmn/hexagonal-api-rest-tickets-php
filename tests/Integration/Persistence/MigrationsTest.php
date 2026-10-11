<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * The test database is built by running the migrations (make test-db, CI): if they and the
 * mapping drift apart, these fail.
 */
final class MigrationsTest extends KernelTestCase
{
    public function test_every_migration_has_been_applied(): void
    {
        $this->assertCommandSucceeds('doctrine:migrations:up-to-date');
    }

    public function test_the_migrated_schema_matches_the_mapping(): void
    {
        $this->assertCommandSucceeds('doctrine:schema:validate');
    }

    /**
     * Through the application, not a CommandTester: the migrations bundle hides its own table from
     * doctrine:schema:validate on the console events, which a CommandTester does not dispatch.
     */
    private function assertCommandSucceeds(string $name): void
    {
        $application = new Application(self::bootKernel());
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);

        $this->assertSame(Command::SUCCESS, $tester->run(['command' => $name], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]), $tester->getDisplay());
    }
}
