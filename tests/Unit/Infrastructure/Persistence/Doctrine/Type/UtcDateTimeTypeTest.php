<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Infrastructure\Persistence\Doctrine\Type\UtcDateTimeType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

/**
 * The base behaviour is covered in TaskDueDateTypeTest; this is what is specific to the plain type.
 */
final class UtcDateTimeTypeTest extends TestCase
{
    public function test_reads_the_plain_value_object(): void
    {
        $date = new UtcDateTimeType()->convertToPHPValue('2026-01-01 12:00:00+02', new PostgreSQLPlatform());

        $this->assertNotNull($date);
        $this->assertSame(DateTime::class, $date::class);
        $this->assertSame('2026-01-01T10:00:00+00:00', (string) $date);
    }

    public function test_writes_subclasses_too_so_it_can_type_query_parameters(): void
    {
        $type = new UtcDateTimeType();

        $this->assertSame('2030-01-01 10:00:00+0000', $type->convertToDatabaseValue(TaskDueDate::fromString('2030-01-01T12:00:00+02:00'), new PostgreSQLPlatform()));
        $this->assertSame('2030-01-01 10:00:00+0000', $type->convertToDatabaseValue(DateTime::fromString('2030-01-01T10:00:00Z'), new PostgreSQLPlatform()));
    }

    public function test_same_column_definition_as_before(): void
    {
        // Was datetimetz_immutable: same SQL, so switching types needs no migration.
        $this->assertSame('TIMESTAMP(0) WITH TIME ZONE', new UtcDateTimeType()->getSQLDeclaration([], new PostgreSQLPlatform()));
    }
}
