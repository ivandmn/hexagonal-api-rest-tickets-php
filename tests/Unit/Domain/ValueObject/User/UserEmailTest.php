<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject\User;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\User\UserEmail;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    use AssertsDomainError;

    public function test_creates_valid_user_email_and_converts_to_lowercase(): void
    {
        $email = new UserEmail('Test@Example.COM');

        $this->assertSame('test@example.com', $email->value());
        $this->assertSame('test@example.com', (string) $email);
    }

    public function test_throws_invalid_user_email_exception_for_invalid_format(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new UserEmail('invalid-email'));
    }

    public function test_inherits_behavior_from_email_value_object(): void
    {
        $a = new UserEmail('user@example.com');
        $b = new UserEmail('user@example.com');

        $this->assertTrue($a->equals($b), 'Same value must be equal');
        $this->assertNotSame($a, $b, 'Equal value objects are still distinct instances');
    }

    public function test_throws_exception_for_too_long_email(): void
    {
        $localPart = \str_repeat('a', 250);
        $tooLong   = $localPart . '@example.com';
        $this->assertDomainError(InvalidValueException::TOO_LONG, static fn () => new UserEmail($tooLong));
    }
}
