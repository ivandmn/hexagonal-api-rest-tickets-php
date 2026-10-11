<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\DeleteTask;

use App\Application\Command\DeleteTask\DeleteTaskCommand;
use App\Domain\Exception\InvalidValueException;
use App\Tests\Support\AssertsDomainError;
use PHPUnit\Framework\TestCase;

final class DeleteTaskCommandTest extends TestCase
{
    use AssertsDomainError;

    public function test_builds_the_task_id(): void
    {
        $this->assertSame('018f9f9a-aaaa-bbbb-cccc-000000000001', new DeleteTaskCommand('018f9f9a-aaaa-bbbb-cccc-000000000001')->getTaskId()->value());
    }

    public function test_rejects_an_invalid_id_when_built(): void
    {
        $this->assertDomainError(InvalidValueException::INVALID_FORMAT, static fn () => new DeleteTaskCommand(''), ['expected' => 'UUID']);
    }
}
