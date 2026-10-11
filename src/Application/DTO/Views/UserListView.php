<?php

declare(strict_types=1);

namespace App\Application\DTO\Views;

/**
 * Every user plus the total.
 */
final readonly class UserListView
{
    /**
     * @param list<UserView> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
