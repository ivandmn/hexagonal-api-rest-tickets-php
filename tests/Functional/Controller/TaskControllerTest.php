<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
use App\Domain\Factory\TaskFactory;
use App\Domain\Model\Task;
use App\Domain\Model\User;
use App\Domain\Repository\TaskRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Task\TaskDescription;
use App\Domain\ValueObject\Task\TaskDueDate;
use App\Domain\ValueObject\Task\TaskId;
use App\Domain\ValueObject\Task\TaskPriority;
use App\Domain\ValueObject\Task\TaskStatus;
use App\Domain\ValueObject\Task\TaskTitle;
use App\Domain\ValueObject\User\UserEmail;
use App\Domain\ValueObject\User\UserId;
use App\Domain\ValueObject\User\UserName;
use App\Tests\Support\AssertsProblemResponse;
use App\Tests\Support\FutureDate;
use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TaskControllerTest extends WebTestCase
{
    use AssertsProblemResponse;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    private function createTask(
        string $title = 'Test Task',
        string $description = 'Valid Description',
        TaskPriority $priority = TaskPriority::LOW,
        ?\DateTimeImmutable $due = null,
    ): Task {
        /** @var TaskFactory $factory */
        $factory = static::getContainer()->get(TaskFactory::class);

        $task = $factory->register(
            TaskTitle::fromString($title),
            TaskDescription::fromString($description),
            $priority,
            $due ? TaskDueDate::fromDate($due) : null,
            null,
        );

        /** @var TaskRepositoryInterface $repo */
        $repo = static::getContainer()->get(TaskRepositoryInterface::class);
        $repo->create($task);

        return $task;
    }

    private function createUser(string $email = 'user@example.com', string $name = 'Valid Name'): User
    {
        $user = new User(UserId::new(), UserEmail::fromString($email), UserName::fromString($name), new \DateTimeImmutable('now'));
        /** @var UserRepositoryInterface $repo */
        $repo = static::getContainer()->get(UserRepositoryInterface::class);
        $repo->save($user);

        return $user;
    }

    // ---------- LIST ----------
    public function test_list_returns_204_when_empty(): void
    {
        $this->client->request('GET', '/api/tasks');
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
    }

    public function test_list_returns_200_with_payload(): void
    {
        $this->createTask('Task One', 'Desc 1', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+2 days'));
        $this->createTask('Task Two', 'Desc 2', TaskPriority::HIGH, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request('GET', '/api/tasks');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('data', $json['data']);
        $this->assertArrayHasKey('meta', $json['data']);
        $this->assertGreaterThanOrEqual(2, $json['data']['meta']['total']);
    }

    public function test_list_with_filters_and_pagination(): void
    {
        $this->createTask('High P1', 'Desc', TaskPriority::HIGH, new \DateTimeImmutable('now')->modify('+2 days'));
        $this->createTask('High P2', 'Desc', TaskPriority::HIGH, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request('GET', '/api/tasks?status=pending&priority=high&page=1&limit=1');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertCount(1, $json['data']['data']);
        $this->assertSame(1, $json['data']['meta']['page']);
    }

    public function test_list_returns_422_on_invalid_param_type(): void
    {
        $this->client->request('GET', '/api/tasks?page=abc');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['page' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_list_returns_422_on_unsupported_priority_value(): void
    {
        $this->client->request('GET', '/api/tasks?priority=test');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['priority' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_list_returns_422_on_unsupported_status_value(): void
    {
        $this->client->request('GET', '/api/tasks?status=unknown');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['status' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_create_returns_200_on_valid_payload(): void
    {
        $payload = [
            'title'       => 'Test Task',
            'description' => 'This is a valid task.',
            'priority'    => 'high',
            'dueDate'     => FutureDate::on(time: '10:00:00')->format(\DATE_ATOM),
        ];

        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame('Test Task', $json['data']['title']);
        $this->assertSame('high', $json['data']['priority']);
    }

    public function test_create_returns_422_listing_every_missing_field(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['description' => 'Missing Title.']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => InvalidValueException::REQUIRED, 'priority' => InvalidValueException::REQUIRED]);
    }

    public function test_create_returns_422_on_extra_parameter_not_allowed(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([
                'title'       => 'Test Task',
                'description' => 'Has extra',
                'priority'    => 'low',
                'dueDate'     => FutureDate::on(time: '10:00:00')->format(\DATE_ATOM),
                'unexpected'  => 'value',
            ]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['unexpected' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_create_returns_422_on_invalid_parameter_type(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([
                'title'       => 12345,
                'description' => 'x',
                'priority'    => 'low',
                'dueDate'     => FutureDate::on(time: '10:00:00')->format(\DATE_ATOM),
            ]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_create_returns_422_on_invalid_priority_enum(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([
                'title'       => 'Test Task',
                'description' => 'Priority must be low, medium or high.',
                'priority'    => 'urgent',
                'dueDate'     => FutureDate::on(time: '10:00:00')->format(\DATE_ATOM),
            ]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['priority' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_create_returns_422_on_invalid_due_date_format(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([
                'title'       => 'Test Task',
                'description' => 'Invalid Due Date Format.',
                'priority'    => 'high',
                'dueDate'     => '2030-01-01 10:00:00',
            ]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['dueDate' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_create_returns_422_on_due_date_in_past(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([
                'title'       => 'Test Task',
                'description' => 'Due date cannot be in the past.',
                'priority'    => 'medium',
                'dueDate'     => '2020-01-01T10:00:00+00:00',
            ]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['dueDate' => TaskDueDateInPastException::ERROR_CODE]);
    }

    public function test_get_details_returns_200_on_valid_id(): void
    {
        $task = $this->createTask('Details Task', 'D', TaskPriority::MEDIUM, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request('GET', '/api/tasks/' . $task->getId()->value());
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('success', $json['status']);
        $this->assertSame($task->getId()->value(), $json['data']['id']);
    }

    public function test_get_details_returns_404_on_invalid_id_format(): void
    {
        $this->client->request('GET', '/api/tasks/12345');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_get_details_returns_404_on_not_found(): void
    {
        $this->client->request('GET', '/api/tasks/018f63a8-bb93-7e5b-b0e1-49d24fa4ffff');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    // ---------- UPDATE ----------
    public function test_update_returns_200_on_full_update(): void
    {
        $task = $this->createTask('Updatable Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $payload = [
            'title'       => 'Updated Task Title',
            'description' => 'This is an updated task.',
            'status'      => 'in_progress',
            'priority'    => 'high',
            'dueDate'     => FutureDate::on(time: '10:00:00')->format(\DATE_ATOM),
        ];

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload),
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function test_update_returns_200_on_partial_update(): void
    {
        $task = $this->createTask('Partial Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['title' => 'Only Update Title']),
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function test_update_returns_422_on_unexpected_field(): void
    {
        $task = $this->createTask('Unexpected Field Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['title' => 'Updated Task Title', 'unexpected' => 'value']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['unexpected' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_update_returns_422_on_invalid_field_type(): void
    {
        $task = $this->createTask('Type Error Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['title' => 12345]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_update_returns_404_on_invalid_id_format(): void
    {
        $this->client->request(
            'PUT',
            '/api/tasks/invalid-id',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['title' => 'Updated Task Title']),
        );

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_update_returns_404_on_not_found(): void
    {
        $this->client->request(
            'PUT',
            '/api/tasks/018f63a8-bb93-7e5b-b0e1-49d24fa4ffff',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['title' => 'Updated Task Title']),
        );

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_update_returns_422_on_invalid_status_enum(): void
    {
        $task = $this->createTask('Bad Status Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['status' => 'unknown_status']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['status' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_update_returns_422_on_invalid_priority_enum(): void
    {
        $task = $this->createTask('Bad Priority Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['priority' => 'urgent']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['priority' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_update_returns_422_on_invalid_due_date_format(): void
    {
        $task = $this->createTask('Bad Date Format Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['dueDate' => '2030-01-01 10:00:00']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['dueDate' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_update_returns_422_on_due_date_in_past(): void
    {
        $task = $this->createTask('Past Date Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+4 days'));

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['dueDate' => '2020-01-01T10:00:00+00:00']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, TaskDueDateInPastException::ERROR_CODE, ['dueDate' => TaskDueDateInPastException::ERROR_CODE]);
    }

    public function test_update_rejects_unsupported_status_transition(): void
    {
        $task = new Task(
            TaskId::new(),
            TaskTitle::fromString('Pending Task'),
            TaskDescription::fromString('Valid'),
            TaskStatus::PENDING,
            TaskPriority::LOW,
            null,
            TaskDueDate::fromDate(new \DateTimeImmutable('now')->modify('+3 days')),
            new \DateTimeImmutable('now'),
            null,
        );
        /** @var TaskRepositoryInterface $repo */
        $repo = static::getContainer()->get(TaskRepositoryInterface::class);
        $repo->create($task);

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['status' => 'completed']),
        );

        $problem = $this->assertProblem($this->client, Response::HTTP_CONFLICT, InvalidTaskStatusTransitionException::ERROR_CODE, ['status' => InvalidTaskStatusTransitionException::ERROR_CODE]);
        $this->assertSame(['from' => 'pending', 'to' => 'completed'], $problem['params']);
    }

    public function test_assign_returns_200_on_success(): void
    {
        $task = $this->createTask('Assignable Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));
        $user = $this->createUser('assign@example.com', 'Assign Target');

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => $user->getId()->value()]),
        );
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function test_assign_returns_422_on_missing_required_parameter(): void
    {
        $task = $this->createTask('Assign Missing', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode([]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['userId' => InvalidValueException::REQUIRED]);
    }

    public function test_assign_returns_422_on_extra_parameter_not_allowed(): void
    {
        $task = $this->createTask('Assign Extra', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));
        $user = $this->createUser('extra@example.com', 'Extra User');

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => $user->getId()->value(), 'extra' => 'x']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['extra' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_assign_returns_422_on_invalid_user_id_type(): void
    {
        $task = $this->createTask('Assign Type', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => 12345]),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['userId' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_assign_returns_404_on_user_not_found(): void
    {
        $task = $this->createTask('Assign Not Found', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => '018f63a8-bb93-7e5b-b0e1-49d24fa4ffff']),
        );

        $problem = $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, UserNotFoundException::ERROR_CODE);
        $this->assertSame(['userId' => '018f63a8-bb93-7e5b-b0e1-49d24fa4ffff'], $problem['params']);
    }

    public function test_assign_returns_404_on_task_not_found(): void
    {
        $user = $this->createUser('assign2@example.com', 'Assign Target 2');

        $this->client->request(
            'PATCH',
            '/api/tasks/018f63a8-bb93-7e5b-b0e1-49d24fa4ffff/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => $user->getId()->value()]),
        );

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_assign_rejects_invalid_ids(): void
    {
        $user = $this->createUser('assign3@example.com', 'Assign Target 3');

        $this->client->request(
            'PATCH',
            '/api/tasks/12345/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => $user->getId()->value()]),
        );

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');

        $task = $this->createTask('Assign Invalid UserId', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));
        $this->client->request(
            'PATCH',
            '/api/tasks/' . $task->getId()->value() . '/assign',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode(['userId' => 'invalid-uuid']),
        );

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['userId' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_delete_returns_200_or_204_on_pending_task(): void
    {
        $pending = $this->createTask('Deletable Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));

        $this->client->request('DELETE', '/api/tasks/' . $pending->getId()->value());
        $this->assertTrue(\in_array($this->client->getResponse()->getStatusCode(), [Response::HTTP_OK, Response::HTTP_NO_CONTENT], true));
    }

    public function test_delete_returns_404_on_invalid_id_format(): void
    {
        $this->client->request('DELETE', '/api/tasks/invalid-id');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_delete_returns_404_on_not_found(): void
    {
        $this->client->request('DELETE', '/api/tasks/018f63a8-bb93-7e5b-b0e1-49d24fa4ffff');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_delete_returns_409_on_in_progress_task(): void
    {
        $t = $this->createTask('Undeletable Task', 'D', TaskPriority::LOW, new \DateTimeImmutable('now')->modify('+3 days'));
        /** @var TaskRepositoryInterface $tasks */
        $tasks  = static::getContainer()->get(TaskRepositoryInterface::class);
        $loaded = $tasks->findById(TaskId::fromString($t->getId()->value()));
        $loaded->changeStatus(TaskStatus::IN_PROGRESS);
        $tasks->update($loaded);

        $this->client->request('DELETE', '/api/tasks/' . $t->getId()->value());

        $problem = $this->assertProblem($this->client, Response::HTTP_CONFLICT, TaskDeletionNotAllowedException::ERROR_CODE);
        $this->assertSame('in_progress', $problem['params']['status']);
    }

    public function test_list_paginates_without_filters_and_reports_the_real_total(): void
    {
        $this->createTask('Paged Task One');
        $this->createTask('Paged Task Two');
        $this->createTask('Paged Task Three');

        $this->client->request('GET', '/api/tasks?limit=2');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $json['data']['data']);
        $this->assertSame(3, $json['data']['meta']['total']);
        $this->assertSame(2, $json['data']['meta']['limit']);
    }

    public function test_list_returns_422_when_limit_exceeds_the_maximum(): void
    {
        $this->client->request('GET', '/api/tasks?limit=101');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['limit' => InvalidValueException::OUT_OF_RANGE]);
    }

    public function test_create_without_due_date(): void
    {
        $this->client->request(
            'POST',
            '/api/tasks',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: \json_encode(['title' => 'No due date', 'description' => 'Optional field omitted', 'priority' => 'low']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($json['data']['dueDate']);
    }

    public function test_update_with_null_due_date_removes_it(): void
    {
        $task = $this->createTask('Has a due date', 'D', TaskPriority::LOW, FutureDate::on());

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: \json_encode(['dueDate' => null]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($json['data']['dueDate']);
    }

    public function test_update_without_due_date_key_keeps_it(): void
    {
        $task = $this->createTask('Keeps its due date', 'D', TaskPriority::LOW, FutureDate::on());

        $this->client->request(
            'PUT',
            '/api/tasks/' . $task->getId()->value(),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: \json_encode(['title' => 'Renamed, same due date']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $json = \json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(FutureDate::on()->format(\DATE_ATOM), $json['data']['dueDate']);
    }
}
