<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskIdType extends Type
{
    public const NAME = 'task_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TaskId) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', TaskId::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskId
    {
        if (null === $value || $value instanceof TaskId) {
            return $value;
        }

        return TaskId::fromString((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
