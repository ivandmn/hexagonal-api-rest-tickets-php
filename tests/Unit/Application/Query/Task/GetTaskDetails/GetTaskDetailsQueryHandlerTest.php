<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query\Task\GetTaskDetails;

use App\Application\Query\Task\GetTaskDetails\GetTaskDetailsQuery;
use App\Application\Query\Task\GetTaskDetails\GetTaskDetailsQueryHandler;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\ValueObject\Task\TaskId;
use App\Tests\Support\AssertsDomainError;
use App\Tests\Support\Double\InMemoryTaskRepository;
use App\Tests\Support\Mother;
use PHPUnit\Framework\TestCase;

final class GetTaskDetailsQueryHandlerTest extends TestCase
{
    use AssertsDomainError;

    public function test_returns_the_view_of_the_task(): void
    {
        $task = Mother::task(title: 'Details Title');

        $view = new GetTaskDetailsQueryHandler(new InMemoryTaskRepository($task))(new GetTaskDetailsQuery($task->getId()->value()));

        $this->assertSame($task->getId()->value(), $view->id);
        $this->assertSame('Details Title', $view->title);
    }

    public function test_fails_when_the_task_does_not_exist(): void
    {
        $taskId  = TaskId::new()->value();
        $handler = new GetTaskDetailsQueryHandler(new InMemoryTaskRepository());

        $this->assertDomainError(TaskNotFoundException::ERROR_CODE, static fn () => $handler(new GetTaskDetailsQuery($taskId)), ['task_id' => $taskId]);
    }
}
