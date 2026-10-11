<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskStatusType extends Type
{
    public const NAME = 'task_status';

    private const LENGTH = 30;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => self::LENGTH]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TaskStatus) {
            return $value->value;
        }

        throw InvalidType::new($value, self::NAME, ['null', TaskStatus::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskStatus
    {
        if (null === $value || $value instanceof TaskStatus) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', TaskStatus::class]);
        }

        return TaskStatus::fromString($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
