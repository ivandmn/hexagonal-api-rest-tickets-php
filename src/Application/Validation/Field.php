<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Domain\Exception\DomainException;

/**
 * Builds one input of a command or query, tagging a domain failure with the input's name so the
 * caller can tell which field was wrong. The rule itself stays in the Value Object:
 *
 *     $this->title = Field::of('title', static fn (): TaskTitle => TaskTitle::fromString($title));
 */
final class Field
{
    private function __construct()
    {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $build
     *
     * @throws InvalidFieldException
     *
     * @return T
     */
    public static function of(string $name, \Closure $build): mixed
    {
        try {
            return $build();
        } catch (DomainException $error) {
            throw new InvalidFieldException($name, $error);
        }
    }
}
