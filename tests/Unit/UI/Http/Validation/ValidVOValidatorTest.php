<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Validation;

use App\Domain\Exception\DomainException;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\UI\Http\Validation\ValidVO;
use App\UI\Http\Validation\ValidVOValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidVOValidator>
 */
final class ValidVOValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidVOValidator
    {
        return new ValidVOValidator();
    }

    public function test_null_is_left_to_not_blank(): void
    {
        $this->validator->validate(null, new ValidVO(TaskTitle::class));

        $this->assertNoViolation();
    }

    public function test_a_valid_value_passes(): void
    {
        $this->validator->validate('A valid title', new ValidVO(TaskTitle::class));

        $this->assertNoViolation();
    }

    public function test_an_invalid_value_becomes_a_violation_carrying_the_domain_error(): void
    {
        $this->validator->validate('abc', new ValidVO(TaskTitle::class));

        $violations = $this->context->getViolations();
        $this->assertCount(1, $violations);
        $this->assertSame(InvalidValueException::TOO_SHORT, $violations->get(0)->getCode());

        $cause = $violations->get(0)->getCause();
        $this->assertInstanceOf(DomainException::class, $cause);
        $this->assertSame(['min' => TaskTitle::MIN_LENGTH], $cause->params());
    }

    public function test_uses_the_named_constructor_when_given(): void
    {
        $this->validator->validate('in_progress', new ValidVO(TaskStatus::class, factory: 'fromString'));
        $this->assertNoViolation();

        $this->validator->validate('done', new ValidVO(TaskStatus::class, factory: 'fromString'));
        $this->assertSame(InvalidValueException::INVALID_CHOICE, $this->context->getViolations()->get(0)->getCode());
    }

    public function test_rejects_other_constraints(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('x', new NotBlank());
    }
}
