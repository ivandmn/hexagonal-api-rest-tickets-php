<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class TaskStatusTest extends TestCase
{
    use AssertsDomainError;

    public function test_from_string_returns_correct_cases(): void
    {
        $this->assertSame(TaskStatus::PENDING, TaskStatus::fromString('pending'));
        $this->assertSame(TaskStatus::IN_PROGRESS, TaskStatus::fromString('in_progress'));
        $this->assertSame(TaskStatus::COMPLETED, TaskStatus::fromString('completed'));
    }

    public function test_from_string_throws_for_invalid_value(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_CHOICE, static fn () => TaskStatus::fromString('PENDING'));
    }

    public function test_predicates(): void
    {
        $this->assertTrue(TaskStatus::PENDING->isPending());
        $this->assertFalse(TaskStatus::PENDING->isInProgress());
        $this->assertFalse(TaskStatus::PENDING->isCompleted());

        $this->assertTrue(TaskStatus::IN_PROGRESS->isInProgress());
        $this->assertFalse(TaskStatus::IN_PROGRESS->isPending());
        $this->assertFalse(TaskStatus::IN_PROGRESS->isCompleted());

        $this->assertTrue(TaskStatus::COMPLETED->isCompleted());
        $this->assertFalse(TaskStatus::COMPLETED->isPending());
        $this->assertFalse(TaskStatus::COMPLETED->isInProgress());
    }

    public function test_equals(): void
    {
        $this->assertTrue(TaskStatus::PENDING->equals(TaskStatus::PENDING));
        $this->assertFalse(TaskStatus::PENDING->equals(TaskStatus::COMPLETED));
    }

    public function test_valid_transitions(): void
    {
        $this->assertTrue(TaskStatus::PENDING->canTransitionTo(TaskStatus::IN_PROGRESS));
        $this->assertTrue(TaskStatus::IN_PROGRESS->canTransitionTo(TaskStatus::COMPLETED));
    }

    public function test_invalid_transitions(): void
    {
        $this->assertFalse(TaskStatus::PENDING->canTransitionTo(TaskStatus::COMPLETED));
        $this->assertFalse(TaskStatus::COMPLETED->canTransitionTo(TaskStatus::IN_PROGRESS));
        $this->assertFalse(TaskStatus::COMPLETED->canTransitionTo(TaskStatus::PENDING));
        $this->assertFalse(TaskStatus::PENDING->canTransitionTo(TaskStatus::PENDING));
        $this->assertFalse(TaskStatus::IN_PROGRESS->canTransitionTo(TaskStatus::IN_PROGRESS));
        $this->assertFalse(TaskStatus::COMPLETED->canTransitionTo(TaskStatus::COMPLETED));
    }
}
