<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class TaskPriorityTest extends TestCase
{
    use AssertsDomainError;

    public function test_from_string_returns_correct_cases(): void
    {
        $this->assertSame(TaskPriority::LOW, TaskPriority::fromString('low'));
        $this->assertSame(TaskPriority::MEDIUM, TaskPriority::fromString('medium'));
        $this->assertSame(TaskPriority::HIGH, TaskPriority::fromString('high'));
    }

    public function test_from_string_throws_for_invalid_value(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_CHOICE, static fn () => TaskPriority::fromString('LOW'));
    }

    public function test_predicates(): void
    {
        $this->assertTrue(TaskPriority::LOW->isLow());
        $this->assertFalse(TaskPriority::LOW->isMedium());
        $this->assertFalse(TaskPriority::LOW->isHigh());

        $this->assertTrue(TaskPriority::MEDIUM->isMedium());
        $this->assertFalse(TaskPriority::MEDIUM->isLow());
        $this->assertFalse(TaskPriority::MEDIUM->isHigh());

        $this->assertTrue(TaskPriority::HIGH->isHigh());
        $this->assertFalse(TaskPriority::HIGH->isLow());
        $this->assertFalse(TaskPriority::HIGH->isMedium());
    }

    public function test_comparisons_higher_and_lower(): void
    {
        $this->assertTrue(TaskPriority::MEDIUM->isHigherThan(TaskPriority::LOW));
        $this->assertTrue(TaskPriority::HIGH->isHigherThan(TaskPriority::MEDIUM));
        $this->assertFalse(TaskPriority::LOW->isHigherThan(TaskPriority::HIGH));

        $this->assertTrue(TaskPriority::LOW->isLowerThan(TaskPriority::MEDIUM));
        $this->assertTrue(TaskPriority::MEDIUM->isLowerThan(TaskPriority::HIGH));
        $this->assertFalse(TaskPriority::HIGH->isLowerThan(TaskPriority::LOW));
    }

    public function test_equals(): void
    {
        $this->assertTrue(TaskPriority::LOW->equals(TaskPriority::LOW));
        $this->assertFalse(TaskPriority::LOW->equals(TaskPriority::HIGH));
    }

    public function test_numeric_values(): void
    {
        $this->assertSame(1, TaskPriority::LOW->getNumericValue());
        $this->assertSame(2, TaskPriority::MEDIUM->getNumericValue());
        $this->assertSame(3, TaskPriority::HIGH->getNumericValue());
    }
}
