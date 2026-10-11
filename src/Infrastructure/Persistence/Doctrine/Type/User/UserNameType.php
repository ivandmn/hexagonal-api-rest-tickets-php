<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\User;

use App\Domain\ValueObject\User\UserName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class UserNameType extends Type
{
    public const NAME = 'user_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => UserName::MAX_LENGTH]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof UserName) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', UserName::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserName
    {
        if (null === $value || $value instanceof UserName) {
            return $value;
        }

        return UserName::fromString((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
