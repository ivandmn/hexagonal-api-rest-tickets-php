<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class TaskTitleTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_task_title(): void
    {
        $title = new TaskTitle('Valid Title');

        $this->assertInstanceOf(TaskTitle::class, $title);
        $this->assertSame('Valid Title', $title->value());
        $this->assertSame('Valid Title', (string) $title);
    }

    public function test_from_string_creates_valid_task_title(): void
    {
        $title = TaskTitle::fromString('Another Title');

        $this->assertInstanceOf(TaskTitle::class, $title);
        $this->assertSame('Another Title', $title->value());
    }

    public function test_throws_exception_when_too_short(): void
    {
        $this->assertDomainError(InvalidValueException::TOO_SHORT, static fn () => new TaskTitle('abcd'));
    }

    public function test_throws_exception_when_too_long(): void
    {
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new TaskTitle(\str_repeat('a', TaskTitle::MAX_LENGTH + 1)));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $a = new TaskTitle('Same Title');
        $b = new TaskTitle('Same Title');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new TaskTitle('Title One');
        $b = new TaskTitle('Title Two');

        $this->assertFalse($a->equals($b));
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $this->assertSame('Padded value', new TaskTitle("  Padded value \n")->value());
    }

    public function test_whitespace_does_not_count_towards_the_minimum_length(): void
    {
        $this->assertDomainError(InvalidValueException::TOO_SHORT, static fn () => new TaskTitle('   abcd   '), ['min' => TaskTitle::MIN_LENGTH]);
    }

    public function test_lengths_count_characters_not_bytes(): void
    {
        // 5 characters, 10 bytes: long enough by characters...
        $this->assertSame('ÁÉÍÓÚ', new TaskTitle('ÁÉÍÓÚ')->value());
        // ...and the maximum in characters fits even though it is twice as many bytes.
        $this->assertSame(TaskTitle::MAX_LENGTH, \mb_strlen(new TaskTitle(\str_repeat('ñ', TaskTitle::MAX_LENGTH))->value()));
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new TaskTitle(\str_repeat('ñ', TaskTitle::MAX_LENGTH + 1)));
    }
}
