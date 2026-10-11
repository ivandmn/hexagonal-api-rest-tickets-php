<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Exception;

use App\UI\Http\Exception\ErrorMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorMappingTest extends TestCase
{
    public function test_builds_from_a_valid_definition(): void
    {
        $mapping = ErrorMapping::fromConfig('USER_ALREADY_EXISTS', ['status' => 409, 'message' => 'Taken: {email}', 'field' => 'email']);

        $this->assertSame(409, $mapping->status);
        $this->assertSame('Taken: {email}', $mapping->message);
        $this->assertSame('email', $mapping->field);
    }

    public function test_field_is_optional(): void
    {
        $this->assertNull(ErrorMapping::fromConfig('TASK_NOT_FOUND', ['status' => 404, 'message' => 'Missing'])->field);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'not a map' => ['409', 'expected a map'];
        yield 'missing status' => [['message' => 'x'], '"status"'];
        yield 'status as string' => [['status' => '404', 'message' => 'x'], '"status"'];
        yield 'success status' => [['status' => 200, 'message' => 'x'], '"status"'];
        yield 'missing message' => [['status' => 404], '"message"'];
        yield 'blank message' => [['status' => 404, 'message' => '  '], '"message"'];
        yield 'blank field' => [['status' => 404, 'message' => 'x', 'field' => ''], '"field"'];
        yield 'typo in a key' => [['status' => 404, 'mesage' => 'x'], 'unknown key(s) "mesage"'];
    }

    #[DataProvider('invalidDefinitions')]
    public function test_rejects_invalid_definitions_naming_the_code(mixed $definition, string $reason): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"SOME_CODE".*' . \preg_quote($reason, '/') . '/');

        ErrorMapping::fromConfig('SOME_CODE', $definition);
    }
}
