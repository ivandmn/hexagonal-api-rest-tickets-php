<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Exception;

use App\Domain\Exception\InvalidValueException;
use App\UI\Http\Exception\ApiErrorCode;
use App\UI\Http\Exception\ConstraintViolationTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;

final class ConstraintViolationTranslatorTest extends TestCase
{
    private ConstraintViolationTranslator $translator;

    protected function setUp(): void
    {
        $this->translator = new ConstraintViolationTranslator();
    }

    public function test_uses_the_domain_exception_carried_as_cause(): void
    {
        $violation = $this->violation(code: InvalidValueException::TOO_SHORT, cause: InvalidValueException::tooShort(5));

        $this->assertSame(['code' => InvalidValueException::TOO_SHORT, 'params' => ['min' => 5]], $this->translator->translate($violation));
    }

    public function test_translates_required_constraints(): void
    {
        $this->assertSame(['code' => InvalidValueException::REQUIRED, 'params' => []], $this->translator->translate($this->violation(code: Assert\NotBlank::IS_BLANK_ERROR)));
        $this->assertSame(['code' => InvalidValueException::REQUIRED, 'params' => []], $this->translator->translate($this->violation(code: Assert\NotNull::IS_NULL_ERROR)));
    }

    public function test_renames_and_normalises_native_parameters(): void
    {
        $range = $this->violation(code: Assert\Range::NOT_IN_RANGE_ERROR, parameters: ['{{ min }}' => '1', '{{ max }}' => '100', '{{ value }}' => '500']);
        $this->assertSame(['code' => InvalidValueException::OUT_OF_RANGE, 'params' => ['min' => 1, 'max' => 100]], $this->translator->translate($range));

        $length = $this->violation(code: Assert\Length::TOO_LONG_ERROR, parameters: ['{{ limit }}' => '255']);
        $this->assertSame(['code' => InvalidValueException::TOO_LONG, 'params' => ['max' => 255]], $this->translator->translate($length));

        $choice = $this->violation(code: Assert\Choice::NO_SUCH_CHOICE_ERROR, parameters: ['{{ choices }}' => '"low", "high"']);
        $this->assertSame(['code' => InvalidValueException::INVALID_CHOICE, 'params' => ['allowed' => 'low, high']], $this->translator->translate($choice));
    }

    public function test_adds_static_params_for_format_constraints(): void
    {
        $uuid = $this->violation(code: Assert\Uuid::INVALID_CHARACTERS_ERROR);

        $this->assertSame(['code' => InvalidValueException::INVALID_FORMAT, 'params' => ['expected' => 'UUID']], $this->translator->translate($uuid));
    }

    public function test_denormalization_errors_become_invalid_type_without_null(): void
    {
        $violation = $this->violation(parameters: ['{{ type }}' => 'null|string', 'hint' => 'ignored']);

        $this->assertSame(['code' => ApiErrorCode::INVALID_TYPE, 'params' => ['expected' => 'string']], $this->translator->translate($violation));
    }

    public function test_falls_back_to_a_generic_invalid_value(): void
    {
        $this->assertSame(['code' => InvalidValueException::INVALID, 'params' => []], $this->translator->translate($this->violation(code: 'some-unknown-code')));
    }

    /**
     * @param array<string, string> $parameters
     */
    private function violation(?string $code = null, array $parameters = [], mixed $cause = null): ConstraintViolation
    {
        return new ConstraintViolation('message', 'template', $parameters, null, 'field', 'invalid', null, $code, null, $cause);
    }
}
