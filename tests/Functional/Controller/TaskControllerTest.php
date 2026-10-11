<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Exception\InvalidValueException;
use App\Domain\Exception\Task\InvalidTaskStatusTransitionException;
use App\Domain\Exception\Task\TaskDeletionNotAllowedException;
use App\Domain\Exception\Task\TaskDueDateInPastException;
use App\Domain\Exception\Task\TaskNotFoundException;
use App\Domain\Exception\User\UserNotFoundException;
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
use App\Tests\Support\AssertsJsonResponse;
use App\Tests\Support\AssertsProblemResponse;
use App\Tests\Support\FutureDate;
use App\UI\Http\Exception\ApiErrorCode;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TaskControllerTest extends WebTestCase
{
    use AssertsJsonResponse;
    use AssertsProblemResponse;

    private const string UNKNOWN_ID = '018f63a8-bb93-7e5b-b0e1-49d24fa4ffff';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
    }

    // ---------- LIST ----------

    public function test_list_returns_200_with_an_empty_list(): void
    {
        $this->client->request('GET', '/api/tasks');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame([], $json['data']);
        $this->assertSame(['total' => 0, 'page' => 1, 'limit' => 50], $json['meta']);
    }

    public function test_list_returns_the_tasks_newest_first(): void
    {
        $this->givenTask('Task One', createdAt: new \DateTimeImmutable('-2 hours'));
        $this->givenTask('Task Two', createdAt: new \DateTimeImmutable('-1 hour'));

        $this->client->request('GET', '/api/tasks');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame(['Task Two', 'Task One'], \array_column($json['data'], 'title'));
        $this->assertSame(2, $json['meta']['total']);
        $this->assertSnakeCaseKeys($json);
    }

    public function test_list_with_filters_and_pagination(): void
    {
        $this->givenTask('High P1', TaskPriority::HIGH);
        $this->givenTask('High P2', TaskPriority::HIGH);
        $this->givenTask('Low P1', TaskPriority::LOW);

        $this->client->request('GET', '/api/tasks?status=pending&priority=high&page=1&limit=1');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertCount(1, $json['data']);
        $this->assertSame('high', $json['data'][0]['priority']);
        $this->assertSame(['total' => 2, 'page' => 1, 'limit' => 1], $json['meta']);
    }

    public function test_list_page_beyond_the_last_is_an_empty_list(): void
    {
        $this->givenTask('Only task');

        $this->client->request('GET', '/api/tasks?page=5');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame([], $json['data']);
        $this->assertSame(1, $json['meta']['total']);
    }

    public function test_list_paginates_without_filters_and_reports_the_real_total(): void
    {
        $this->givenTask('Paged Task One');
        $this->givenTask('Paged Task Two');
        $this->givenTask('Paged Task Three');

        $this->client->request('GET', '/api/tasks?limit=2');

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertCount(2, $json['data']);
        $this->assertSame(['total' => 3, 'page' => 1, 'limit' => 2], $json['meta']);
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

    public function test_list_returns_422_when_limit_exceeds_the_maximum(): void
    {
        $this->client->request('GET', '/api/tasks?limit=101');

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['limit' => InvalidValueException::OUT_OF_RANGE]);
    }

    // ---------- CREATE ----------

    public function test_create_returns_201_with_the_task_and_its_location(): void
    {
        $dueDate = FutureDate::on(time: '10:00:00')->format(\DATE_ATOM);

        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'This is a valid task.', 'priority' => 'high', 'due_date' => $dueDate]);

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_CREATED);
        $task = $json['data'];
        $this->assertSame(['id', 'title', 'description', 'status', 'priority', 'assigned_to', 'due_date', 'created_at', 'updated_at'], \array_keys($task));
        $this->assertSame('Test Task', $task['title']);
        $this->assertSame('This is a valid task.', $task['description']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame('high', $task['priority']);
        $this->assertNull($task['assigned_to']);
        $this->assertSame($dueDate, $task['due_date']);
        $this->assertNull($task['updated_at']);

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertSame('http://localhost/api/tasks/' . $task['id'], $location);

        // The Location points to the same resource.
        $this->client->request('GET', (string) $location);
        $this->assertSame($task, $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data']);
    }

    public function test_create_without_due_date(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'No due date', 'description' => 'Optional field omitted', 'priority' => 'low']);

        $this->assertNull($this->assertJsonSuccess($this->client, Response::HTTP_CREATED)['data']['due_date']);
    }

    public function test_create_returns_dates_in_utc(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Madrid time', 'description' => 'D', 'priority' => 'low', 'due_date' => FutureDate::on(time: '12:00:00')->format('Y-m-d') . 'T12:00:00+02:00']);

        $this->assertSame(FutureDate::on(time: '10:00:00')->format(\DATE_ATOM), $this->assertJsonSuccess($this->client, Response::HTTP_CREATED)['data']['due_date']);
    }

    public function test_create_returns_422_on_the_first_missing_field(): void
    {
        $this->sendJson('POST', '/api/tasks', ['description' => 'Missing Title.']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => InvalidValueException::REQUIRED]);
    }

    public function test_create_returns_422_on_a_null_required_field(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => null, 'description' => 'Null title', 'priority' => 'low']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_create_returns_422_on_extra_parameter_not_allowed(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'Has extra', 'priority' => 'low', 'unexpected' => 'value']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['unexpected' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_create_rejects_camel_case_keys(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'D', 'priority' => 'low', 'dueDate' => FutureDate::on()->format(\DATE_ATOM)]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['dueDate' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_create_returns_422_on_invalid_parameter_type(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 12345, 'description' => 'x', 'priority' => 'low']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_create_returns_422_on_invalid_priority_enum(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'Priority must be low, medium or high.', 'priority' => 'urgent']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['priority' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_create_returns_422_on_invalid_due_date_format(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'Invalid Due Date Format.', 'priority' => 'high', 'due_date' => '2030-01-01 10:00:00']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['due_date' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_create_rejects_an_overflowing_due_date_instead_of_rolling_it_over(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'February 30th', 'priority' => 'high', 'due_date' => '2030-02-30T10:00:00+00:00']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['due_date' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_create_returns_422_on_due_date_in_past(): void
    {
        $this->sendJson('POST', '/api/tasks', ['title' => 'Test Task', 'description' => 'Due date cannot be in the past.', 'priority' => 'medium', 'due_date' => '2020-01-01T10:00:00+00:00']);

        $problem = $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, TaskDueDateInPastException::ERROR_CODE, ['due_date' => TaskDueDateInPastException::ERROR_CODE]);
        $this->assertSame(['due_date' => '2020-01-01T10:00:00+00:00'], $problem['params']);
    }

    // ---------- DETAILS ----------

    public function test_get_details_returns_200_on_valid_id(): void
    {
        $task = $this->givenTask('Details Task', TaskPriority::MEDIUM, FutureDate::on());

        $this->client->request('GET', '/api/tasks/' . $task->getId()->value());

        $json = $this->assertJsonSuccess($this->client, Response::HTTP_OK);
        $this->assertSame($task->getId()->value(), $json['data']['id']);
        $this->assertSame('medium', $json['data']['priority']);
        $this->assertSame(FutureDate::on()->format(\DATE_ATOM), $json['data']['due_date']);
    }

    public function test_get_details_returns_404_on_invalid_id_format(): void
    {
        $this->client->request('GET', '/api/tasks/12345');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_get_details_returns_404_on_not_found(): void
    {
        $this->client->request('GET', '/api/tasks/' . self::UNKNOWN_ID);

        $problem = $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
        $this->assertSame(['task_id' => self::UNKNOWN_ID], $problem['params']);
    }

    // ---------- UPDATE (PATCH) ----------

    public function test_update_changes_every_field_sent(): void
    {
        $task    = $this->givenTask('Updatable Task', due: FutureDate::on(day: 4));
        $dueDate = FutureDate::on(time: '10:00:00')->format(\DATE_ATOM);

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), [
            'title'       => 'Updated Task Title',
            'description' => 'This is an updated task.',
            'status'      => 'in_progress',
            'priority'    => 'high',
            'due_date'    => $dueDate,
        ]);

        $data = $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data'];
        $this->assertSame('Updated Task Title', $data['title']);
        $this->assertSame('This is an updated task.', $data['description']);
        $this->assertSame('in_progress', $data['status']);
        $this->assertSame('high', $data['priority']);
        $this->assertSame($dueDate, $data['due_date']);
        $this->assertNotNull($data['updated_at']);
    }

    public function test_update_leaves_fields_not_sent_unchanged(): void
    {
        $task = $this->givenTask('Partial Task', TaskPriority::MEDIUM, FutureDate::on());

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['title' => 'Only Update Title']);

        $data = $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data'];
        $this->assertSame('Only Update Title', $data['title']);
        $this->assertSame('medium', $data['priority']);
        $this->assertSame(FutureDate::on()->format(\DATE_ATOM), $data['due_date']);
    }

    public function test_update_with_null_due_date_removes_it(): void
    {
        $task = $this->givenTask('Has a due date', due: FutureDate::on());

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['due_date' => null]);

        $this->assertNull($this->assertJsonSuccess($this->client, Response::HTTP_OK)['data']['due_date']);
    }

    public function test_update_without_changes_returns_the_unchanged_task(): void
    {
        $task = $this->givenTask('Same title');

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['title' => 'Same title']);

        $this->assertNull($this->assertJsonSuccess($this->client, Response::HTTP_OK)['data']['updated_at']);
    }

    public function test_update_is_not_available_with_put(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PUT', '/api/tasks/' . $task->getId()->value(), ['title' => 'Whatever title']);

        $this->assertProblem($this->client, Response::HTTP_METHOD_NOT_ALLOWED, 'METHOD_NOT_ALLOWED');
    }

    public function test_update_returns_422_on_unexpected_field(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['title' => 'Updated Task Title', 'unexpected' => 'value']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['unexpected' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_update_returns_422_on_invalid_field_type(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['title' => 12345]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['title' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_update_returns_404_on_invalid_id_format(): void
    {
        $this->sendJson('PATCH', '/api/tasks/invalid-id', ['title' => 'Updated Task Title']);

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_update_returns_404_on_not_found(): void
    {
        $this->sendJson('PATCH', '/api/tasks/' . self::UNKNOWN_ID, ['title' => 'Updated Task Title']);

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_update_returns_422_on_invalid_status_enum(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['status' => 'unknown_status']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['status' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_update_returns_422_on_invalid_priority_enum(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['priority' => 'urgent']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['priority' => InvalidValueException::INVALID_CHOICE]);
    }

    public function test_update_returns_422_on_invalid_due_date_format(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['due_date' => '2030-01-01 10:00:00']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['due_date' => InvalidValueException::INVALID_FORMAT]);
    }

    public function test_update_returns_422_on_due_date_in_past(): void
    {
        $task = $this->givenTask(due: FutureDate::on(day: 4));

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['due_date' => '2020-01-01T10:00:00+00:00']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, TaskDueDateInPastException::ERROR_CODE, ['due_date' => TaskDueDateInPastException::ERROR_CODE]);
    }

    public function test_update_rejects_unsupported_status_transition(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value(), ['status' => 'completed']);

        $problem = $this->assertProblem($this->client, Response::HTTP_CONFLICT, InvalidTaskStatusTransitionException::ERROR_CODE, ['status' => InvalidTaskStatusTransitionException::ERROR_CODE]);
        $this->assertSame(['from' => 'pending', 'to' => 'completed'], $problem['params']);
    }

    // ---------- ASSIGN ----------

    public function test_assign_returns_the_task_with_its_assignee(): void
    {
        $task = $this->givenTask('Assignable Task');
        $user = $this->givenUser('assign@example.com', 'Assign Target');

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', ['user_id' => $user->getId()->value()]);

        $data = $this->assertJsonSuccess($this->client, Response::HTTP_OK)['data'];
        $this->assertSame($task->getId()->value(), $data['id']);
        $this->assertSame(['id' => $user->getId()->value(), 'name' => 'Assign Target'], $data['assigned_to']);
    }

    public function test_assign_returns_422_on_missing_required_parameter(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', []);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['user_id' => InvalidValueException::REQUIRED]);
    }

    public function test_assign_returns_422_on_extra_parameter_not_allowed(): void
    {
        $task = $this->givenTask();
        $user = $this->givenUser('extra@example.com', 'Extra User');

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', ['user_id' => $user->getId()->value(), 'extra' => 'x']);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['extra' => ApiErrorCode::UNKNOWN_FIELD]);
    }

    public function test_assign_returns_422_on_invalid_user_id_type(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', ['user_id' => 12345]);

        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['user_id' => ApiErrorCode::INVALID_TYPE]);
    }

    public function test_assign_returns_404_on_user_not_found(): void
    {
        $task = $this->givenTask();

        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', ['user_id' => self::UNKNOWN_ID]);

        $problem = $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, UserNotFoundException::ERROR_CODE);
        $this->assertSame(['user_id' => self::UNKNOWN_ID], $problem['params']);
    }

    public function test_assign_returns_404_on_task_not_found(): void
    {
        $user = $this->givenUser('assign2@example.com', 'Assign Target 2');

        $this->sendJson('PATCH', '/api/tasks/' . self::UNKNOWN_ID . '/assign', ['user_id' => $user->getId()->value()]);

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_assign_rejects_invalid_ids(): void
    {
        $user = $this->givenUser('assign3@example.com', 'Assign Target 3');

        $this->sendJson('PATCH', '/api/tasks/12345/assign', ['user_id' => $user->getId()->value()]);
        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');

        $task = $this->givenTask();
        $this->sendJson('PATCH', '/api/tasks/' . $task->getId()->value() . '/assign', ['user_id' => 'invalid-uuid']);
        $this->assertProblem($this->client, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION_ERROR, ['user_id' => InvalidValueException::INVALID_FORMAT]);
    }

    // ---------- DELETE ----------

    public function test_delete_returns_204_on_pending_task(): void
    {
        $pending = $this->givenTask('Deletable Task');

        $this->client->request('DELETE', '/api/tasks/' . $pending->getId()->value());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/tasks/' . $pending->getId()->value());
        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_delete_returns_404_on_invalid_id_format(): void
    {
        $this->client->request('DELETE', '/api/tasks/invalid-id');

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, 'NOT_FOUND');
    }

    public function test_delete_returns_404_on_not_found(): void
    {
        $this->client->request('DELETE', '/api/tasks/' . self::UNKNOWN_ID);

        $this->assertProblem($this->client, Response::HTTP_NOT_FOUND, TaskNotFoundException::ERROR_CODE);
    }

    public function test_delete_returns_409_on_in_progress_task(): void
    {
        $task = $this->givenTask(status: TaskStatus::IN_PROGRESS);

        $this->client->request('DELETE', '/api/tasks/' . $task->getId()->value());

        $problem = $this->assertProblem($this->client, Response::HTTP_CONFLICT, TaskDeletionNotAllowedException::ERROR_CODE);
        $this->assertSame(['task_id' => $task->getId()->value(), 'status' => 'in_progress'], $problem['params']);
    }

    // ---------- helpers ----------

    /**
     * @param array<string, mixed> $payload
     */
    private function sendJson(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: \json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function givenTask(
        string $title = 'Test Task',
        TaskPriority $priority = TaskPriority::LOW,
        ?\DateTimeImmutable $due = null,
        TaskStatus $status = TaskStatus::PENDING,
        \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ): Task {
        $task = new Task(
            TaskId::new(),
            TaskTitle::fromString($title),
            TaskDescription::fromString('Valid Description'),
            $status,
            $priority,
            null,
            null !== $due ? TaskDueDate::fromDateTime($due) : null,
            $createdAt,
            null,
        );

        /** @var TaskRepositoryInterface $repo */
        $repo = static::getContainer()->get(TaskRepositoryInterface::class);
        $repo->create($task);

        return $task;
    }

    private function givenUser(string $email, string $name): User
    {
        $user = User::create(UserId::new(), UserEmail::fromString($email), UserName::fromString($name), new \DateTimeImmutable());

        /** @var UserRepositoryInterface $repo */
        $repo = static::getContainer()->get(UserRepositoryInterface::class);
        $repo->save($user);

        return $user;
    }
}
