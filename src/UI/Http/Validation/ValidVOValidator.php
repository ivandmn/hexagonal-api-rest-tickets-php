<?php

declare(strict_types=1);

namespace App\UI\Http\Validation;

use App\Domain\Exception\DomainException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidVOValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidVO) {
            throw new UnexpectedTypeException($constraint, ValidVO::class);
        }

        // Missing values are NotBlank / NotNull's job.
        if (null === $value) {
            return;
        }

        try {
            null === $constraint->factory
                ? new ($constraint->class)($value)
                : ($constraint->class)::{$constraint->factory}($value);
        } catch (DomainException $exception) {
            $this->context->buildViolation($exception->getMessage())
                ->setCode($exception->errorCode())
                // Required: the error listener reads the error code and params from the cause.
                ->setCause($exception)
                ->addViolation()
            ;
        }
    }
}
