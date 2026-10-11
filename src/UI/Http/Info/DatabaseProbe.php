<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Talks to the database for real; kept out of the page render (fetched lazily) so a slow or
 * unreachable server never blocks /info.
 */
final readonly class DatabaseProbe
{
    public function __construct(
        private Connection $connection,
        private SecretMasker $masker,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $migrations,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        $connection = $this->connection;
        $params     = $connection->getParams();
        $result     = ['Target' => \sprintf('%s:%s/%s', $params['host'] ?? '?', $params['port'] ?? '?', $params['dbname'] ?? '?')];

        try {
            $start = \hrtime(true);
            $connection->executeQuery('SELECT 1')->fetchOne();
            $result['Connected']      = Block::badge('yes', 'ok');
            $result['Round trip']     = \round((\hrtime(true) - $start) / 1e6, 2) . ' ms';
            $result['Server version'] = $connection->getServerVersion();

            if ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $row = $connection->fetchAssociative(
                    'SELECT current_database() AS db, current_user AS usr, inet_server_addr()::text AS addr,
                            pg_size_pretty(pg_database_size(current_database())) AS size,
                            pg_postmaster_start_time() AS started,
                            (SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()) AS sessions',
                );
                $result += [
                    'Database'            => $row['db'] ?? null,
                    'User'                => $row['usr'] ?? null,
                    'Server address'      => $row['addr'] ?? null,
                    'Database size'       => $row['size'] ?? null,
                    'Server started'      => $row['started'] ?? null,
                    'Sessions on this DB' => $row['sessions'] ?? null,
                ];
            }
        } catch (\Throwable $exception) {
            return $result + [
                'Connected' => Block::badge('no', 'danger'),
                'Error'     => $this->masker->maskUrl($exception->getMessage()),
            ];
        }

        try {
            $available = $this->migrations->getMigrationRepository()->getMigrations()->count();
            $executed  = $this->migrations->getMetadataStorage()->getExecutedMigrations()->count();
            $new       = \array_map(
                static fn (object $migration): string => (string) $migration->getVersion(),
                $this->migrations->getMigrationStatusCalculator()->getNewMigrations()->getItems(),
            );

            $result += [
                'Migrations available' => $available,
                'Migrations executed'  => $executed,
                'Pending migrations'   => [] === $new ? Block::badge('none', 'ok') : \array_values($new),
            ];
        } catch (\Throwable $exception) {
            $result['Migrations'] = Block::badge('unavailable: ' . $exception->getMessage(), 'warn');
        }

        return $result;
    }
}
