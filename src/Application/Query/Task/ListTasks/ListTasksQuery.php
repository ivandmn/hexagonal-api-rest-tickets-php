<?php

declare(strict_types=1);

namespace App\Application\Query\Task\ListTasks;

use App\Application\Query\QueryInterface;
use App\Application\Validation\Field;
use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\User\UserId;

final readonly class ListTasksQuery implements QueryInterface
{
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT     = 100;

    private ?TaskStatus $status;
    private ?TaskPriority $priority;
    private ?UserId $assignedTo;
    private ?DateTime $dueFrom;
    private ?DateTime $dueTo;
    private int $page;
    private int $limit;

    /**
     * @param string|null $assignedTo id of the user the tasks are assigned to
     * @param string|null $dueFrom    RFC 3339; due on or after it (past dates are fine: it is a filter)
     * @param string|null $dueTo      RFC 3339; due on or before it
     */
    public function __construct(
        ?string $status = null,
        ?string $priority = null,
        ?string $assignedTo = null,
        ?string $dueFrom = null,
        ?string $dueTo = null,
        int $page = 1,
        int $limit = self::DEFAULT_LIMIT,
    ) {
        $this->status     = null !== $status ? Field::of('status', static fn (): TaskStatus => TaskStatus::fromString($status)) : null;
        $this->priority   = null !== $priority ? Field::of('priority', static fn (): TaskPriority => TaskPriority::fromString($priority)) : null;
        $this->assignedTo = null !== $assignedTo ? Field::of('assignedTo', static fn (): UserId => UserId::fromString($assignedTo)) : null;
        $this->dueFrom    = null !== $dueFrom ? Field::of('dueFrom', static fn (): DateTime => DateTime::fromString($dueFrom)) : null;
        $this->dueTo      = null !== $dueTo ? Field::of('dueTo', fn (): DateTime => $this->notBeforeDueFrom(DateTime::fromString($dueTo))) : null;
        $this->page       = Field::of('page', static fn (): int => $page >= 1 ? $page : throw InvalidValueException::belowMinimum(1, $page));
        $this->limit      = Field::of('limit', static fn (): int => $limit >= 1 && $limit <= self::MAX_LIMIT ? $limit : throw InvalidValueException::outOfRange(1, self::MAX_LIMIT, $limit));
    }

    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    public function getPriority(): ?TaskPriority
    {
        return $this->priority;
    }

    public function getAssignedTo(): ?UserId
    {
        return $this->assignedTo;
    }

    public function getDueFrom(): ?DateTime
    {
        return $this->dueFrom;
    }

    public function getDueTo(): ?DateTime
    {
        return $this->dueTo;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @throws InvalidValueException
     */
    private function notBeforeDueFrom(DateTime $dueTo): DateTime
    {
        if (null !== $this->dueFrom && $dueTo->isBefore($this->dueFrom)) {
            throw InvalidValueException::invalid('due_to cannot be before due_from');
        }

        return $dueTo;
    }
}
