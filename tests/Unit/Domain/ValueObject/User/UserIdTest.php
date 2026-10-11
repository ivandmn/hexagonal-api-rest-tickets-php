<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\User;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\User\UserId;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UserIdTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_user_id(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $id         = new UserId($uuidString);

        $this->assertSame($uuidString, $id->value());
        $this->assertSame($uuidString, (string) $id);
    }

    public function test_from_string_creates_valid_user_id(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $id         = UserId::fromString($uuidString);

        $this->assertSame($uuidString, $id->value());
    }

    public function test_new_creates_valid_user_id(): void
    {
        $id = UserId::new();

        $this->assertTrue(RamseyUuid::isValid($id->value()));
        $this->assertSame(36, \strlen($id->value()));
    }

    public function test_throws_invalid_user_id_exception_for_invalid_uuid(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new UserId('invalid-uuid'));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $a          = new UserId($uuidString);
        $b          = new UserId($uuidString);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $a = new UserId(RamseyUuid::uuid4()->toString());
        $b = new UserId(RamseyUuid::uuid4()->toString());

        $this->assertFalse($a->equals($b));
    }
}
