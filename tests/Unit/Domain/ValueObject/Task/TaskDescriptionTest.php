<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class TaskDescriptionTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_task_description(): void
    {
        $desc = new TaskDescription('Valid description');

        $this->assertInstanceOf(TaskDescription::class, $desc);
        $this->assertSame('Valid description', $desc->value());
        $this->assertSame('Valid description', (string) $desc);
    }

    public function test_from_string_creates_valid_task_description(): void
    {
        $desc = TaskDescription::fromString('Another description');

        $this->assertInstanceOf(TaskDescription::class, $desc);
        $this->assertSame('Another description', $desc->value());
    }

    public function test_rejects_a_blank_description(): void
    {
        $this->assertDomainError(InvalidValueException::REQUIRED, static fn () => new TaskDescription(''));
        $this->assertDomainError(InvalidValueException::REQUIRED, static fn () => new TaskDescription('   '));
    }

    public function test_throws_exception_when_too_long(): void
    {
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new TaskDescription(\str_repeat('a', TaskDescription::MAX_LENGTH + 1)));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $a = new TaskDescription('Same');
        $b = new TaskDescription('Same');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new TaskDescription('One');
        $b = new TaskDescription('Two');

        $this->assertFalse($a->equals($b));
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame('Some description', new TaskDescription("\t Some description  ")->value());
    }

    public function test_max_length_counts_characters_not_bytes(): void
    {
        $this->assertSame(TaskDescription::MAX_LENGTH, \mb_strlen(new TaskDescription(\str_repeat('ü', TaskDescription::MAX_LENGTH))->value()));
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new TaskDescription(\str_repeat('ü', TaskDescription::MAX_LENGTH + 1)));
    }
}
