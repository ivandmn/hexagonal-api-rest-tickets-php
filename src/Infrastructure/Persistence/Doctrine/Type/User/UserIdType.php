<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\User;

use App\Domain\ValueObject\User\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class UserIdType extends Type
{
    public const NAME = 'user_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof UserId) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', UserId::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserId
    {
        if (null === $value || $value instanceof UserId) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', UserId::class]);
        }

        return UserId::fromString($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
