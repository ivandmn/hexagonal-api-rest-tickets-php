<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskTitle;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskTitleType extends Type
{
    public const NAME = 'task_title';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => TaskTitle::MAX_LENGTH]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TaskTitle) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', TaskTitle::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskTitle
    {
        if (null === $value || $value instanceof TaskTitle) {
            return $value;
        }

        return TaskTitle::fromString((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
