<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Validation\InvalidFieldException;
use App\Domain\Exception\DomainException;

/**
 * Asserts on the stable error code (and optionally the params) of a domain exception, which is
 * what clients and the HTTP layer rely on, rather than on its class or internal message.
 */
trait AssertsDomainError
{
    /**
     * @param array<string, scalar|null>|null $params expected params, or null to skip the check
     */
    private function assertDomainError(string $errorCode, callable $action, ?array $params = null): DomainException
    {
        try {
            $action();
        } catch (DomainException $exception) {
            self::assertSame($errorCode, $exception->errorCode(), 'Unexpected domain error code');

            if (null !== $params) {
                self::assertSame($params, $exception->params(), 'Unexpected domain error params');
            }

            return $exception;
        }

        self::fail(\sprintf('Expected a domain exception with error code "%s", none was thrown.', $errorCode));
    }

    /**
     * Same, for a command or query input: the domain error must come tagged with the input name.
     *
     * @param array<string, scalar|null>|null $params expected params, or null to skip the check
     */
    private function assertFieldError(string $field, string $errorCode, callable $action, ?array $params = null): DomainException
    {
        try {
            $action();
        } catch (InvalidFieldException $exception) {
            self::assertSame($field, $exception->field, 'Unexpected field');

            return $this->assertDomainError($errorCode, static fn () => throw $exception->error, $params);
        }

        self::fail(\sprintf('Expected field "%s" to be rejected with error code "%s", nothing was thrown.', $field, $errorCode));
    }
}
