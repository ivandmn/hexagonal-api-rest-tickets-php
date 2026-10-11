<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\CreateTask;

use App\Application\Command\CreateTask\CreateTaskCommand;
use App\Application\Command\CreateTask\CreateTaskCommandHandler;
use App\Application\Service\LoggerInterface;
use App\Domain\Exception\Task\InvalidTaskTitleException;
use App\Domain\Factory\TaskFactory;
use App\Domain\Model\Task;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskTitle;
use PHPUnit\Framework\TestCase;

final class CreateTaskCommandHandlerTest extends TestCase
{
    public function test_happy_path_creates_task_persists_and_logs(): void
    {
        $repo   = $this->createMock(TaskRepositoryInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $request = new CreateTaskCommand(
            'Test Title',
            'Test Description',
            'medium',
            '2030-01-01T00:00:00+00:00',
        );

        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(static fn (Task $task) => $task->getTitle()->equals(TaskTitle::fromString('Test Title'))
                    && $task->getDescription()->equals(TaskDescription::fromString('Test Description'))
                    && $task->getPriority()->equals(TaskPriority::fromString('medium'))
                    && true === $task->getDueDate()?->equals(TaskDueDate::fromDate(new \DateTimeImmutable('2030-01-01T00:00:00+00:00')))))
        ;

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Task Created',
                $this->callback(static fn (array $ctx) => isset($ctx['task_id'], $ctx['task_title']) && 'Test Title' === $ctx['task_title']),
            )
        ;

        $uc       = new CreateTaskCommandHandler(new TaskFactory(), $repo, $logger);
        $response = $uc($request);

        $this->assertNotNull($response);
    }

    public function test_propagates_domain_exception_on_invalid_title(): void
    {
        $repo   = $this->createStub(TaskRepositoryInterface::class);
        $logger = $this->createStub(LoggerInterface::class);

        $uc = new CreateTaskCommandHandler(new TaskFactory(), $repo, $logger);

        $this->expectException(InvalidTaskTitleException::class);

        $uc(new CreateTaskCommand(
            'bad',
            'Ok description',
            'low',
            '2030-01-01T00:00:00+00:00',
        ));
    }
}
