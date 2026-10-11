<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskDescriptionType;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskIdType;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskPriorityType;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskStatusType;
use App\Infrastructure\Persistence\Doctrine\Type\Task\TaskTitleType;
use App\Infrastructure\Persistence\Doctrine\Type\User\UserEmailType;
use App\Infrastructure\Persistence\Doctrine\Type\User\UserIdType;
use App\Infrastructure\Persistence\Doctrine\Type\User\UserNameType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The value object types other than the dates (TaskDueDateTypeTest): one value object <-> one column.
 */
final class ValueObjectTypesTest extends TestCase
{
    private const string UUID = '0199d2a4-3b1c-4f5e-9a7b-1c2d3e4f5a6b';

    /**
     * @return iterable<string, array{Type, object, string}>
     */
    public static function types(): iterable
    {
        yield 'task_id'          => [new TaskIdType(), TaskId::fromString(self::UUID), self::UUID];
        yield 'task_title'       => [new TaskTitleType(), TaskTitle::fromString('Fix login redirect'), 'Fix login redirect'];
        yield 'task_description' => [new TaskDescriptionType(), TaskDescription::fromString('Users land on a 404'), 'Users land on a 404'];
        yield 'task_status'      => [new TaskStatusType(), TaskStatus::IN_PROGRESS, 'in_progress'];
        yield 'task_priority'    => [new TaskPriorityType(), TaskPriority::HIGH, 'high'];
        yield 'user_id'          => [new UserIdType(), UserId::fromString(self::UUID), self::UUID];
        yield 'user_email'       => [new UserEmailType(), UserEmail::fromString('jane.doe@example.com'), 'jane.doe@example.com'];
        yield 'user_name'        => [new UserNameType(), UserName::fromString('Jane Doe'), 'Jane Doe'];
    }

    #[DataProvider('types')]
    public function test_writes_the_primitive_value(Type $type, object $value, string $stored): void
    {
        $this->assertSame($stored, $type->convertToDatabaseValue($value, new PostgreSQLPlatform()));
    }

    #[DataProvider('types')]
    public function test_reads_back_the_same_value(Type $type, object $value, string $stored): void
    {
        $platform = new PostgreSQLPlatform();
        $read     = $type->convertToPHPValue($stored, $platform);

        $this->assertIsObject($read);
        $this->assertSame($value::class, $read::class);
        $this->assertSame($stored, $type->convertToDatabaseValue($read, $platform));
    }

    #[DataProvider('types')]
    public function test_a_value_object_read_again_is_returned_as_is(Type $type, object $value, string $stored): void
    {
        $this->assertSame($value, $type->convertToPHPValue($value, new PostgreSQLPlatform()));
    }

    #[DataProvider('types')]
    public function test_null_passes_through(Type $type, object $value, string $stored): void
    {
        $this->assertNull($type->convertToDatabaseValue(null, new PostgreSQLPlatform()));
        $this->assertNull($type->convertToPHPValue(null, new PostgreSQLPlatform()));
    }

    #[DataProvider('types')]
    public function test_rejects_a_raw_string_on_write(Type $type, object $value, string $stored): void
    {
        $this->expectException(InvalidType::class);

        $type->convertToDatabaseValue($stored, new PostgreSQLPlatform());
    }

    #[DataProvider('types')]
    public function test_rejects_a_non_string_on_read(Type $type, object $value, string $stored): void
    {
        $this->expectException(InvalidType::class);

        $type->convertToPHPValue(42, new PostgreSQLPlatform());
    }

    public function test_every_type_is_registered_under_its_name(): void
    {
        $config = Yaml::parseFile(\dirname(__DIR__, 6) . '/config/packages/doctrine.yaml');
        \assert(\is_array($config) && \is_array($config['doctrine']) && \is_array($config['doctrine']['dbal']) && \is_array($config['doctrine']['dbal']['types']));
        $registered = $config['doctrine']['dbal']['types'];

        foreach (self::types() as $name => [$type]) {
            $this->assertSame($name, \constant($type::class . '::NAME'));
            $this->assertSame($type::class, $registered[$name] ?? null, \sprintf('"%s" is not registered in doctrine.yaml', $name));
        }
    }

    public function test_emails_are_stored_lower_case(): void
    {
        $this->assertSame('jane.doe@example.com', new UserEmailType()->convertToDatabaseValue(UserEmail::fromString('Jane.Doe@Example.com'), new PostgreSQLPlatform()));
    }
}
