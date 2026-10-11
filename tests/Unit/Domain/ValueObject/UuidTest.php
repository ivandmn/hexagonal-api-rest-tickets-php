<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Uuid;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;

final class UuidTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_uuid_from_string(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $uuid       = new Uuid($uuidString);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuidString, $uuid->value());
        $this->assertSame($uuidString, (string) $uuid);
    }

    public function test_from_string_creates_valid_uuid(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $uuid       = Uuid::fromString($uuidString);

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertSame($uuidString, $uuid->value());
    }

    public function test_throws_exception_for_invalid_uuid_format(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new Uuid('invalid-uuid'));
    }

    public function test_new_creates_valid_uuid(): void
    {
        $uuid = Uuid::new();

        $this->assertInstanceOf(Uuid::class, $uuid);
        $this->assertTrue(RamseyUuid::isValid($uuid->value()));
        $this->assertSame(36, \strlen($uuid->value()));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $uuidString = RamseyUuid::uuid4()->toString();
        $a          = new Uuid($uuidString);
        $b          = new Uuid($uuidString);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new Uuid(RamseyUuid::uuid4()->toString());
        $b = new Uuid(RamseyUuid::uuid4()->toString());

        $this->assertFalse($a->equals($b));
    }

    public function test_value_object_is_immutable(): void
    {
        $a = new Uuid(RamseyUuid::uuid4()->toString());
        $b = new Uuid($a->value());

        $this->assertTrue($a->equals($b), 'Same value must be equal');
        $this->assertNotSame($a, $b, 'Equal value objects are still distinct instances');
    }
}
