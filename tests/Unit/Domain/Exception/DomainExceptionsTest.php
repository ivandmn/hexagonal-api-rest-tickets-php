<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exception;

use App\Domain\Exception\DomainException;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserAlreadyExistsException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainExceptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{DomainException, string, array<string, scalar|null>}>
     */
    public static function exceptions(): iterable
    {
        $taskId = TaskId::fromString('3f1c2a9e-8b4d-4c6e-9a7f-2d5b8e1c4a63');
        $userId = UserId::fromString('b7e4d2c1-5a3f-4e8b-8c9d-6f1a2b3c4d5e');

        yield 'task not found' => [new TaskNotFoundException($taskId), 'TASK_NOT_FOUND', ['task_id' => $taskId->value()]];
        yield 'transition' => [new InvalidTaskStatusTransitionException(TaskStatus::COMPLETED, TaskStatus::PENDING), 'TASK_STATUS_TRANSITION_NOT_ALLOWED', ['from' => 'completed', 'to' => 'pending']];
        yield 'deletion' => [new TaskDeletionNotAllowedException($taskId, TaskStatus::IN_PROGRESS), 'TASK_DELETION_NOT_ALLOWED', ['task_id' => $taskId->value(), 'status' => 'in_progress']];
        yield 'due date in past' => [new TaskDueDateInPastException(new \DateTimeImmutable('2020-01-01T10:00:00+00:00')), 'TASK_DUE_DATE_IN_PAST', ['due_date' => '2020-01-01T10:00:00+00:00']];
        yield 'user not found' => [new UserNotFoundException($userId), 'USER_NOT_FOUND', ['user_id' => $userId->value()]];
        yield 'user exists' => [new UserAlreadyExistsException(UserEmail::fromString('Jane@Example.com')), 'USER_ALREADY_EXISTS', ['email' => 'jane@example.com']];
        yield 'required' => [InvalidValueException::required(), 'VALUE_REQUIRED', []];
        yield 'invalid' => [InvalidValueException::invalid('nope'), 'VALUE_INVALID', []];
        yield 'format' => [InvalidValueException::invalidFormat('UUID'), 'VALUE_INVALID_FORMAT', ['expected' => 'UUID']];
        yield 'choice' => [InvalidValueException::invalidChoice('x', ['a', 'b']), 'VALUE_INVALID_CHOICE', ['allowed' => 'a, b']];
        yield 'range' => [InvalidValueException::outOfRange(1, 10, 11), 'VALUE_OUT_OF_RANGE', ['min' => 1, 'max' => 10]];
        yield 'below' => [InvalidValueException::belowMinimum(1, 0), 'VALUE_BELOW_MINIMUM', ['min' => 1]];
        yield 'above' => [InvalidValueException::aboveMaximum(10, 11), 'VALUE_ABOVE_MAXIMUM', ['max' => 10]];
        yield 'too short' => [InvalidValueException::tooShort(5), 'VALUE_TOO_SHORT', ['min' => 5]];
        yield 'too long' => [InvalidValueException::tooLong(255), 'VALUE_TOO_LONG', ['max' => 255]];
    }

    /**
     * @param array<string, scalar|null> $params
     */
    #[DataProvider('exceptions')]
    public function test_exposes_a_stable_code_and_its_params(DomainException $exception, string $errorCode, array $params): void
    {
        $this->assertSame($errorCode, $exception->errorCode());
        $this->assertSame($params, $exception->params());
        $this->assertNotSame('', $exception->getMessage(), 'Internal message for logs');
        $this->assertSame(0, $exception->getCode(), 'The native code is not used for business codes');
    }

    public function test_keeps_the_previous_exception(): void
    {
        $previous  = new \RuntimeException('driver error');
        $exception = new UserAlreadyExistsException(UserEmail::fromString('jane@example.com'), $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
