<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\Task;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskId;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class TaskIdTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_task_id(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $id         = new TaskId($uuidString);

        $this->assertSame($uuidString, $id->value());
        $this->assertSame($uuidString, (string) $id);
    }

    public function test_from_string_creates_valid_task_id(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $id         = TaskId::fromString($uuidString);

        $this->assertSame($uuidString, $id->value());
    }

    public function test_new_creates_valid_task_id(): void
    {
        $id = TaskId::new();

        $this->assertTrue(RamseyUuid::isValid($id->value()));
        $this->assertSame(36, \strlen($id->value()));
    }

    public function test_throws_invalid_task_id_exception_for_invalid_uuid(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new TaskId('invalid-uuid'));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $a          = new TaskId($uuidString);
        $b          = new TaskId($uuidString);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $a = new TaskId(RamseyUuid::uuid4()->toString());
        $b = new TaskId(RamseyUuid::uuid4()->toString());

        $this->assertFalse($a->equals($b));
    }
}
