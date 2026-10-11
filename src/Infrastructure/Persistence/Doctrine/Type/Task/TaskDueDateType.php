<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskDueDate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskDueDateType extends Type
{
    public const NAME = 'task_due_date';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTzTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TaskDueDate) {
            return $value->value()->format($platform->getDateTimeTzFormatString());
        }

        throw InvalidType::new($value, self::NAME, ['null', TaskDueDate::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskDueDate
    {
        if (null === $value || $value instanceof TaskDueDate) {
            return $value;
        }

        $date = \DateTimeImmutable::createFromFormat($platform->getDateTimeTzFormatString(), (string) $value);

        if (false === $date) {
            throw InvalidFormat::new((string) $value, self::NAME, $platform->getDateTimeTzFormatString());
        }

        return self::reconstitute($date);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * "Due date cannot be in the past" is a rule for setting a due date, not for reading one back:
     * a stored due date legitimately ends up in the past. Hydrate it the way Doctrine hydrates
     * entities, without running the constructor invariant.
     */
    private static function reconstitute(\DateTimeImmutable $date): TaskDueDate
    {
        $dueDate = new \ReflectionClass(TaskDueDate::class)->newInstanceWithoutConstructor();
        new \ReflectionProperty(TaskDueDate::class, 'date')->setValue($dueDate, $date);

        return $dueDate;
    }
}
