<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\Task;

use App\Domain\ValueObject\Task\TaskDescription;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class TaskDescriptionType extends Type
{
    public const NAME = 'task_description';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => TaskDescription::MAX_LENGTH]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof TaskDescription) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', TaskDescription::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaskDescription
    {
        if (null === $value || $value instanceof TaskDescription) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', TaskDescription::class]);
        }

        return TaskDescription::fromString($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
