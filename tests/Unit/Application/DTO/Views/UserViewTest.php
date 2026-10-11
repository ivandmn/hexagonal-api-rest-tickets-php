<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DTO\Views;

use App\Application\DTO\Views\UserView;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class UserViewTest extends TestCase
{
    public function test_maps_every_field_of_the_user(): void
    {
        $user = Mother::user(email: 'jane@example.com', name: 'Jane Doe');

        $view = UserView::fromUser($user);

        $this->assertSame($user->getId()->value(), $view->id);
        $this->assertSame('jane@example.com', $view->email);
        $this->assertSame('Jane Doe', $view->name);
        $this->assertSame($user->getCreatedAt(), $view->createdAt);
        $this->assertNull($view->updatedAt);
    }
}
