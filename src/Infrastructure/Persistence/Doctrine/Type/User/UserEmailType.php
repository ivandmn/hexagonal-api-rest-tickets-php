<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type\User;

use App\Domain\ValueObject\User\UserEmail;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class UserEmailType extends Type
{
    public const NAME = 'user_email';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => UserEmail::MAX_LENGTH]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof UserEmail) {
            return $value->value();
        }

        throw InvalidType::new($value, self::NAME, ['null', UserEmail::class]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserEmail
    {
        if (null === $value || $value instanceof UserEmail) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, self::NAME, ['null', 'string', UserEmail::class]);
        }

        return UserEmail::fromString($value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
