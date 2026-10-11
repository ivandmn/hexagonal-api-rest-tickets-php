<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\ValueObject\Task\TaskDueDate;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskDueDateType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the DateTimeValueType base through its TaskDueDate type.
 */
final class TaskDueDateTypeTest extends TestCase
{
    private TaskDueDateType $type;

    protected function setUp(): void
    {
        $this->type = new TaskDueDateType();
    }

    public function test_postgres_column_keeps_the_time_zone(): void
    {
        $this->assertSame('TIMESTAMP(0) WITH TIME ZONE', $this->type->getSQLDeclaration([], new PostgreSQLPlatform()));
    }

    public function test_writes_utc_whatever_offset_was_received(): void
    {
        $due = TaskDueDate::fromString('2030-05-06T18:00:00+02:00');

        $this->assertSame('2030-05-06 16:00:00+0000', $this->type->convertToDatabaseValue($due, new PostgreSQLPlatform()));
        $this->assertSame('2030-05-06 16:00:00', $this->type->convertToDatabaseValue($due, new MySQLPlatform()));
        $this->assertSame('2030-05-06 16:00:00', $this->type->convertToDatabaseValue($due, new SQLitePlatform()));
    }

    /**
     * What each platform hands back for the same instant (Postgres answers in the session time zone).
     *
     * @return iterable<string, array{AbstractPlatform, string}>
     */
    public static function storedValues(): iterable
    {
        yield 'postgres, utc session' => [new PostgreSQLPlatform(), '2030-05-06 16:00:00+00'];
        yield 'postgres, madrid session' => [new PostgreSQLPlatform(), '2030-05-06 18:00:00+02'];
        yield 'postgres, negative offset' => [new PostgreSQLPlatform(), '2030-05-06 11:00:00-05'];
        yield 'postgres, fractional seconds' => [new PostgreSQLPlatform(), '2030-05-06 16:00:00.75+00'];
        yield 'mysql, no offset means utc' => [new MySQLPlatform(), '2030-05-06 16:00:00'];
        yield 'sqlite, no offset means utc' => [new SQLitePlatform(), '2030-05-06 16:00:00'];
    }

    #[DataProvider('storedValues')]
    public function test_reads_the_same_instant_back(AbstractPlatform $platform, string $stored): void
    {
        $due = $this->type->convertToPHPValue($stored, $platform);

        $this->assertInstanceOf(TaskDueDate::class, $due);
        $this->assertSame('2030-05-06T16:00:00+00:00', (string) $due);
        $this->assertSame('UTC', $due->value()->getTimezone()->getName());
    }

    public function test_reading_does_not_depend_on_the_php_default_time_zone(): void
    {
        $previous = \date_default_timezone_get();
        \date_default_timezone_set('America/New_York');

        try {
            $due = $this->type->convertToPHPValue('2030-05-06 16:00:00', new MySQLPlatform());
        } finally {
            \date_default_timezone_set($previous);
        }

        $this->assertSame('2030-05-06T16:00:00+00:00', (string) $due);
    }

    #[DataProvider('platforms')]
    public function test_round_trip_keeps_equality(AbstractPlatform $platform): void
    {
        // Milliseconds and a non-UTC offset: neither survives storage, so neither is kept in memory.
        $due    = TaskDueDate::fromString('2030-05-06T18:00:00.987+02:00');
        $stored = $this->type->convertToDatabaseValue($due, $platform);
        $this->assertNotNull($stored);

        $loaded = $this->type->convertToPHPValue($stored, $platform);

        $this->assertNotNull($loaded);
        $this->assertTrue($due->equals($loaded));
        $this->assertSame((string) $due, (string) $loaded);
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function platforms(): iterable
    {
        yield 'postgres' => [new PostgreSQLPlatform()];
        yield 'mysql' => [new MySQLPlatform()];
        yield 'sqlite' => [new SQLitePlatform()];
    }

    public function test_a_past_due_date_loads_back(): void
    {
        $due = $this->type->convertToPHPValue('2020-01-01 10:00:00+00', new PostgreSQLPlatform());

        $this->assertSame('2020-01-01T10:00:00+00:00', (string) $due);
    }

    public function test_null_passes_through(): void
    {
        $platform = new PostgreSQLPlatform();

        $this->assertNull($this->type->convertToDatabaseValue(null, $platform));
        $this->assertNull($this->type->convertToPHPValue(null, $platform));
    }

    public function test_rejects_other_values_on_write(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue(new \DateTimeImmutable(), new PostgreSQLPlatform());
    }

    public function test_rejects_unparseable_values_on_read(): void
    {
        $this->expectException(InvalidFormat::class);

        $this->type->convertToPHPValue('not a date', new PostgreSQLPlatform());
    }
}
