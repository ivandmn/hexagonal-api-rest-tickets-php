<?php

declare(strict_types=1);

namespace App\Domain\Event;

interface EventInterface
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
