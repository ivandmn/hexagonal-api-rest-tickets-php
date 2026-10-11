<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\DTO\Views;

use App\Application\DTO\Views\TaskView;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class TaskViewTest extends TestCase
{
    public function test_maps_every_field_of_the_task(): void
    {
        $user = Mother::user(name: 'Jane Doe');
        $task = Mother::task(
            title: 'Write the docs',
            status: TaskStatus::IN_PROGRESS,
            priority: TaskPriority::HIGH,
            assignee: $user,
            dueDate: TaskDueDate::fromString('2026-07-01T09:00:00+00:00'),
        );

        $view = TaskView::fromTask($task);

        $this->assertSame($task->getId()->value(), $view->id);
        $this->assertSame('Write the docs', $view->title);
        $this->assertSame('Some description', $view->description);
        $this->assertSame('in_progress', $view->status);
        $this->assertSame('high', $view->priority);
        $this->assertNotNull($view->assignedTo);
        $this->assertSame($user->getId()->value(), $view->assignedTo->id);
        $this->assertSame('Jane Doe', $view->assignedTo->name);
        $this->assertSame('2026-07-01T09:00:00+00:00', $view->dueDate?->format(\DATE_ATOM));
        $this->assertSame($task->getCreatedAt(), $view->createdAt);
        $this->assertNull($view->updatedAt);
    }

    public function test_optional_fields_stay_null(): void
    {
        $view = TaskView::fromTask(Mother::task());

        $this->assertNull($view->assignedTo);
        $this->assertNull($view->dueDate);
    }
}
