<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\ValueObject\DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

/**
 * Maps a column to a DateTime Value Object (or a subclass): one concrete type per class, like the
 * Uuid-based ids.
 *
 * Stored as TIMESTAMP WITH TIME ZONE where the platform has it. Values are always written in UTC,
 * so platforms without time zone support (MySQL DATETIME, SQLite) keep the right instant too, and a
 * value read back without an offset is taken as UTC instead of the PHP default time zone.
 *
 * @template T of DateTime
 */
abstract class DateTimeValueType extends Type
{
    /**
     * @return class-string<T>
     */
    abstract protected function valueObjectClass(): string;

    abstract public function getName(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTzTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $class = $this->valueObjectClass();
        if ($value instanceof $class) {
            return $value->value()->format($platform->getDateTimeTzFormatString());
        }

        throw InvalidType::new($value, $this->getName(), ['null', $class]);
    }

    /**
     * @param mixed $value
     *
     * @return T|null
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTime
    {
        $class = $this->valueObjectClass();

        if (null === $value || $value instanceof $class) {
            return $value;
        }

        if (!\is_string($value)) {
            throw InvalidType::new($value, $this->getName(), ['null', 'string', $class]);
        }

        $utc  = new \DateTimeZone(DateTime::TIMEZONE);
        $date = \DateTimeImmutable::createFromFormat($platform->getDateTimeTzFormatString(), $value, $utc);

        // Fallback for what the platform format does not cover, e.g. fractional seconds when the
        // column has a precision.
        if (false === $date) {
            try {
                $date = new \DateTimeImmutable($value, $utc);
            } catch (\Exception $e) {
                throw InvalidFormat::new($value, $this->getName(), $platform->getDateTimeTzFormatString(), $e);
            }
        }

        // No business rule runs here: a stored date is loaded back as it is.
        return $class::fromDateTime($date);
    }
}
