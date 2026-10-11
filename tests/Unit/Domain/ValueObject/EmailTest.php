<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Email;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_email(): void
    {
        $email = new Email('test@example.com');

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('test@example.com', $email->value());
        $this->assertSame('test@example.com', (string) $email);
    }

    public function test_from_string_creates_valid_email(): void
    {
        $email = Email::fromString('user@domain.com');

        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('user@domain.com', $email->value());
    }

    public function test_throws_exception_for_invalid_email_format(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new Email('invalid-email'));
    }

    public function test_throws_exception_for_email_exceeding_max_length(): void
    {
        $localPart = \str_repeat('a', 250);
        $tooLong   = $localPart . '@example.com';
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new Email($tooLong));
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $a = new Email('same@example.com');
        $b = new Email('same@example.com');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $a = new Email('one@example.com');
        $b = new Email('two@example.com');

        $this->assertFalse($a->equals($b));
    }

    public function test_value_object_is_immutable(): void
    {
        $a = new Email('original@example.com');
        $b = new Email('original@example.com');

        $this->assertTrue($a->equals($b), 'Same value must be equal');
        $this->assertNotSame($a, $b, 'Equal value objects are still distinct instances');
    }
}
