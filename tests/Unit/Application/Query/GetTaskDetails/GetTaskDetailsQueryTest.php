<?php

declare(strict_types=1);

namespace App\Tests\Application\Query\GetTaskDetails;

use App\Application\Query\GetTaskDetails\GetTaskDetailsQuery;
use PHPUnit\Framework\TestCase;

final class GetTaskDetailsQueryTest extends TestCase
{
    public function test_getter_returns_task_id(): void
    {
        $req = new GetTaskDetailsQuery('018f9f9a-aaaa-bbbb-cccc-000000000001');
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', $req->getTaskId());
    }

    public function test_allows_empty_string_currently(): void
    {
        $req = new GetTaskDetailsQuery('');
        $this->assertSame('', $req->getTaskId());
    }
}
