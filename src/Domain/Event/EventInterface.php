<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\DateTime;

/**
 * Something that happened in the domain, named in the past tense (TaskAssigned, UserDeleted...).
 */
interface EventInterface
{
    /**
     * Stable identifier, e.g. "task.assigned": safe to use in logs or as a message name.
     */
    public function eventName(): string;

    public function occurredOn(): DateTime;

    /**
     * Primitives only, snake_case keys.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
