<?php

declare(strict_types=1);

namespace App\UI\Http\Exception;

use App\Domain\Exception\DomainException;
use App\Domain\Exception\InvalidValueException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Turns a validator violation into an error code plus params, so every validation error goes
 * through the same mapping (config/packages/error_mapping.yaml) as domain errors:
 *
 * - raised by a Value Object (#[ValidVO]): the domain exception travels as the violation cause;
 * - raised by a native Symfony constraint: translated with the table below;
 * - type mismatch while denormalizing the payload (no code, "{{ type }}" parameter).
 */
final class ConstraintViolationTranslator
{
    private const UUID = ['expected' => 'UUID'];

    /**
     * Native constraint code => [error code, violation parameter => our param, static params].
     *
     * @var array<string, array{0: string, 1: array<string, string>, 2?: array<string, string>}>
     */
    private const NATIVE = [
        Assert\NotBlank::IS_BLANK_ERROR                   => [InvalidValueException::REQUIRED, []],
        Assert\NotNull::IS_NULL_ERROR                     => [InvalidValueException::REQUIRED, []],
        Assert\Type::INVALID_TYPE_ERROR                   => [ApiErrorCode::INVALID_TYPE, ['type' => 'expected']],
        Assert\Length::TOO_SHORT_ERROR                    => [InvalidValueException::TOO_SHORT, ['limit' => 'min']],
        Assert\Length::TOO_LONG_ERROR                     => [InvalidValueException::TOO_LONG, ['limit' => 'max']],
        Assert\Range::NOT_IN_RANGE_ERROR                  => [InvalidValueException::OUT_OF_RANGE, ['min' => 'min', 'max' => 'max']],
        Assert\Range::TOO_LOW_ERROR                       => [InvalidValueException::BELOW_MINIMUM, ['limit' => 'min']],
        Assert\Range::TOO_HIGH_ERROR                      => [InvalidValueException::ABOVE_MAXIMUM, ['limit' => 'max']],
        Assert\GreaterThanOrEqual::TOO_LOW_ERROR          => [InvalidValueException::BELOW_MINIMUM, ['compared_value' => 'min']],
        Assert\LessThanOrEqual::TOO_HIGH_ERROR            => [InvalidValueException::ABOVE_MAXIMUM, ['compared_value' => 'max']],
        Assert\Choice::NO_SUCH_CHOICE_ERROR               => [InvalidValueException::INVALID_CHOICE, ['choices' => 'allowed']],
        Assert\Email::INVALID_FORMAT_ERROR                => [InvalidValueException::INVALID_FORMAT, [], ['expected' => 'email address']],
        Assert\Uuid::TOO_SHORT_ERROR                      => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::TOO_LONG_ERROR                       => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::INVALID_CHARACTERS_ERROR             => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::INVALID_HYPHEN_PLACEMENT_ERROR       => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::INVALID_VERSION_ERROR                => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::INVALID_TIME_BASED_VERSION_ERROR     => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
        Assert\Uuid::INVALID_VARIANT_ERROR                => [InvalidValueException::INVALID_FORMAT, [], self::UUID],
    ];

    /**
     * @return array{code: string, params: array<string, scalar|null>}
     */
    public function translate(ConstraintViolationInterface $violation): array
    {
        $cause = $violation->getCause();
        if ($cause instanceof DomainException) {
            return ['code' => $cause->errorCode(), 'params' => $cause->params()];
        }

        $parameters = $violation->getParameters();
        $native     = self::NATIVE[$violation->getCode() ?? ''] ?? null;

        if (null !== $native) {
            $params = $native[2] ?? [];
            foreach ($native[1] as $from => $to) {
                if (\array_key_exists('{{ ' . $from . ' }}', $parameters)) {
                    $params[$to] = self::clean($parameters['{{ ' . $from . ' }}']);
                }
            }

            return ['code' => $native[0], 'params' => $params];
        }

        // Payload denormalization errors (e.g. a number where a string is expected) carry no code.
        if (null === $violation->getCode() && \array_key_exists('{{ type }}', $parameters)) {
            // Request DTO properties are nullable so that missing fields report VALUE_REQUIRED; "null" is noise here.
            $types = \array_diff(\explode('|', (string) $parameters['{{ type }}']), ['null']);

            return ['code' => ApiErrorCode::INVALID_TYPE, 'params' => ['expected' => \implode('|', $types)]];
        }

        return ['code' => InvalidValueException::INVALID, 'params' => []];
    }

    /**
     * Validator parameters are display strings ("5", "\"low\", \"high\""): normalise them.
     */
    private static function clean(mixed $value): float|int|string
    {
        $value = \trim((string) $value);

        if (\is_numeric($value)) {
            return $value + 0;
        }

        return \str_replace('"', '', $value);
    }
}
