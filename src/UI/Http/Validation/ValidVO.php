<?php

declare(strict_types=1);

namespace App\UI\Http\Validation;

use Symfony\Component\Validator\Constraint;

/**
 * Validates a request field by building the Value Object that owns the rule, so the rule lives
 * only in the domain. A failing VO becomes a violation on this property, carrying the domain
 * exception as its cause (error code + params for the error response).
 *
 *     #[ValidVO(TaskTitle::class)]                          new TaskTitle($value)
 *     #[ValidVO(TaskStatus::class, factory: 'fromString')]  TaskStatus::fromString($value)
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class ValidVO extends Constraint
{
    /**
     * @param class-string  $class   Value Object to build
     * @param string|null   $factory static named constructor to use instead of `new`
     * @param array<string>|null $groups
     */
    public function __construct(
        public readonly string $class,
        public readonly ?string $factory = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
