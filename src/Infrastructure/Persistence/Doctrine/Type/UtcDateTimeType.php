<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\ValueObject\DateTime;

/**
 * Plain instants without rules of their own (createdAt, updatedAt). Writes any DateTime subclass
 * too, so it also types query parameters compared against those columns (e.g. a due date range).
 *
 * @extends DateTimeValueType<DateTime>
 */
final class UtcDateTimeType extends DateTimeValueType
{
    public const NAME = 'utc_datetime';

    protected function valueObjectClass(): string
    {
        return DateTime::class;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
