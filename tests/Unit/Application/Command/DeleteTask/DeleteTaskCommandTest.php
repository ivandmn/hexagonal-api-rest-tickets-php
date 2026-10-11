<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\DeleteTask;

use App\Application\Command\DeleteTask\DeleteTaskCommand;
use PHPUnit\Framework\TestCase;

final class DeleteTaskCommandTest extends TestCase
{
    public function test_getter_returns_task_id(): void
    {
        $req = new DeleteTaskCommand('018f9f9a-aaaa-bbbb-cccc-000000000001');
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', $req->getTaskId());
    }

    public function test_allows_empty_string_currently(): void
    {
        $req = new DeleteTaskCommand('');
        $this->assertSame('', $req->getTaskId());
    }
}
