<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\User\UserDeletionNotAllowedException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\DateTime;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsProblemResponse;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DeleteUserTest extends WebTestCase
{
    use AssertsProblemResponse;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function test_deletes_a_user_without_tasks(): void
    {
        $user = $this->user('lonely@example.com');

        $this->client->request('DELETE', '/api/v1/users/' . $user->getId()->value());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertNull($this->users()->findById($user->getId()));
    }

    public function test_deleting_a_user_deletes_their_completed_tasks_only(): void
    {
        $user  = $this->user('done@example.com');
        $other = $this->user('other@example.com');
        $mine  = [$this->task($user, TaskStatus::COMPLETED), $this->task($user, TaskStatus::COMPLETED)];
        $their = $this->task($other, TaskStatus::PENDING);

        $this->client->request('DELETE', '/api/v1/users/' . $user->getId()->value());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertNull($this->users()->findById($user->getId()));

        $this->em()->clear();
        foreach ($mine as $taskId) {
            $this->assertNull($this->tasks()->findById($taskId), 'Completed tasks are deleted in cascade');
        }
        $this->assertNotNull($this->tasks()->findById($their), 'Other users\' tasks are left alone');
    }

    /**
     * @return iterable<string, array{list<TaskStatus>, int}>
     */
    public static function unfinishedTasks(): iterable
    {
        yield 'a pending task' => [[TaskStatus::PENDING], 1];
        yield 'a task in progress' => [[TaskStatus::IN_PROGRESS], 1];
        yield 'completed and unfinished tasks' => [[TaskStatus::COMPLETED, TaskStatus::PENDING, TaskStatus::IN_PROGRESS], 2];
    }

    /**
     * @param list<TaskStatus> $statuses
     */
    #[DataProvider('unfinishedTasks')]
    public function test_refuses_while_the_user_has_unfinished_tasks(array $statuses, int $unfinished): void
    {
        $user = $this->user('busy@example.com');
        foreach ($statuses as $status) {
            $this->task($user, $status);
        }

        $this->client->request('DELETE', '/api/v1/users/' . $user->getId()->value());

        $problem = $this->assertProblem($this->client, Response::HTTP_CONFLICT, UserDeletionNotAllowedException::ERROR_CODE);
        $this->assertSame(['user_id' => $user->getId()->value(), 'unfinished_tasks' => $unfinished], $problem['params']);

        $this->em()->clear();
        $this->assertNotNull($this->users()->findById($user->getId()), 'Nothing is deleted');
        $this->assertSame(\count($statuses), $this->tasks()->countByUser($user->getId()));
    }

    public function test_unknown_user(): void
    {
        $userId = UserId::new()->value();

        $this->client->request('DELETE', '/api/v1/users/' . $userId);

        $problem = $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, UserNotFoundException::ERROR_CODE);
        $this->assertSame(['user_id' => $userId], $problem['params']);
        $this->assertArrayNotHasKey('errors', $problem);
    }

    public function test_id_that_is_not_a_uuid(): void
    {
        $this->client->request('DELETE', '/api/v1/users/not-a-uuid');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    private function user(string $email): User
    {
        $user = User::create(UserId::new(), UserEmail::fromString($email), UserName::fromString('Some User'), new \DateTimeImmutable());
        $this->users()->save($user);

        return $user;
    }

    private function task(User $assignee, TaskStatus $status): TaskId
    {
        $taskId = TaskId::new();
        $this->tasks()->create(new Task($taskId, TaskTitle::fromString('Some task'), TaskDescription::fromString('D'), $status, TaskPriority::LOW, $assignee, null, DateTime::fromDateTime(new \DateTimeImmutable()), null));

        return $taskId;
    }

    private function users(): UserRepositoryInterface
    {
        return $this->service(UserRepositoryInterface::class);
    }

    private function tasks(): TaskRepositoryInterface
    {
        return $this->service(TaskRepositoryInterface::class);
    }

    private function em(): EntityManagerInterface
    {
        return $this->service(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = static::getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
