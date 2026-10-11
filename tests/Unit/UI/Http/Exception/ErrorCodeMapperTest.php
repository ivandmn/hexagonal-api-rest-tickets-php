<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Exception;

use App\UI\Http\Exception\ErrorCodeMapper;
use PHPUnit\Framework\TestCase;

final class ErrorCodeMapperTest extends TestCase
{
    private ErrorCodeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ErrorCodeMapper([
            'TASK_NOT_FOUND'     => ['status' => 404, 'message' => 'Task {task_id} does not exist.'],
            'VALUE_OUT_OF_RANGE' => ['status' => 422, 'message' => 'Between {min} and {max} ({strict}{nothing}).'],
        ]);
    }

    public function test_finds_mappings_by_code(): void
    {
        $this->assertSame(404, $this->mapper->find('TASK_NOT_FOUND')?->status);
        $this->assertNull($this->mapper->find('UNKNOWN_CODE'));
        $this->assertSame(['TASK_NOT_FOUND', 'VALUE_OUT_OF_RANGE'], $this->mapper->mappedCodes());
    }

    public function test_renders_placeholders_with_the_exception_params(): void
    {
        $mapping = $this->mapper->find('TASK_NOT_FOUND');
        \assert(null !== $mapping);

        $this->assertSame('Task 42 does not exist.', $this->mapper->render($mapping, ['task_id' => '42']));
    }

    public function test_renders_numbers_booleans_and_nulls(): void
    {
        $mapping = $this->mapper->find('VALUE_OUT_OF_RANGE');
        \assert(null !== $mapping);

        $this->assertSame('Between 1 and 2.5 (true).', $this->mapper->render($mapping, ['min' => 1, 'max' => 2.5, 'strict' => true, 'nothing' => null]));
    }

    public function test_leaves_unknown_placeholders_untouched(): void
    {
        $mapping = $this->mapper->find('TASK_NOT_FOUND');
        \assert(null !== $mapping);

        $this->assertSame('Task {task_id} does not exist.', $this->mapper->render($mapping, []));
    }
}
