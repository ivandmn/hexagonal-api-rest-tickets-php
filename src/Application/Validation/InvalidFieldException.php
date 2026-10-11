<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Exception\DomainException;

/**
 * A command or query input was rejected by the domain. `field` is the input name (camelCase, as
 * in the command constructor); `error` says what was wrong with it.
 */
final class InvalidFieldException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        public readonly DomainException $error,
    ) {
        parent::__construct(\sprintf('Invalid "%s": %s', $field, $error->getMessage()), 0, $error);
    }
}
