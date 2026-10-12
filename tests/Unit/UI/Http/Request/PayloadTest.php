<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Request;

use App\Domain\Exception\InvalidValueException;
use App\UI\Http\Exception\ApiErrorCode;
use App\UI\Http\Request\InvalidPayloadException;
use App\UI\Http\Request\Payload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

final class PayloadTest extends TestCase
{
    public function test_reads_each_type(): void
    {
        $payload = $this->json('{"title": "Fix it", "count": 3, "ratio": 1.5, "whole": 2, "done": false}', ['title', 'count', 'ratio', 'whole', 'done']);

        $this->assertSame('Fix it', $payload->string('title'));
        $this->assertSame(3, $payload->int('count'));
        $this->assertSame(1.5, $payload->float('ratio'));
        $this->assertSame(2.0, $payload->float('whole'), 'a JSON integer is a number too');
        $this->assertFalse($payload->bool('done'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function wrongTypes(): iterable
    {
        yield 'number as string'  => ['{"v": 123}', 'string', 'string'];
        yield 'bool as string'    => ['{"v": true}', 'string', 'string'];
        yield 'array as string'   => ['{"v": ["x"]}', 'string', 'string'];
        yield 'object as string'  => ['{"v": {"a": 1}}', 'string', 'string'];
        yield 'numeric as int'    => ['{"v": "3"}', 'int', 'int'];
        yield 'float as int'      => ['{"v": 1.5}', 'int', 'int'];
        yield 'string as float'   => ['{"v": "1.5"}', 'float', 'float'];
        yield 'int as bool'       => ['{"v": 1}', 'bool', 'bool'];
        yield 'null when required' => ['{"v": null}', 'string', 'string'];
    }

    #[DataProvider('wrongTypes')]
    public function test_a_different_type_is_rejected_not_cast(string $body, string $reader, string $expected): void
    {
        $payload = $this->json($body, ['v']);

        $this->assertPayloadError(ApiErrorCode::INVALID_TYPE, ['v'], static fn () => $payload->{$reader}('v'));
    }

    public function test_a_missing_required_field_is_required(): void
    {
        $payload = $this->json('{}', ['title']);

        $this->assertPayloadError(InvalidValueException::REQUIRED, ['title'], static fn () => $payload->string('title'));
        $this->assertPayloadError(InvalidValueException::REQUIRED, ['title'], static fn () => $payload->int('title'));
    }

    public function test_missing_and_null_are_told_apart(): void
    {
        $payload = $this->json('{"due_date": null}', ['title', 'due_date']);

        $this->assertNull($payload->optionalString('title'));
        $this->assertNull($payload->optionalString('due_date'));
        $this->assertFalse($payload->has('title'));
        $this->assertTrue($payload->has('due_date'));
    }

    public function test_an_empty_json_array_is_an_empty_object(): void
    {
        $this->assertFalse($this->json('[]', ['title'])->has('title'));
    }

    public function test_unknown_fields_are_all_reported(): void
    {
        $this->assertPayloadError(ApiErrorCode::UNKNOWN_FIELD, ['foo', 'bar'], fn () => $this->json('{"title": "x", "foo": 1, "bar": 2}', ['title']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'empty'      => ['', ApiErrorCode::EMPTY_BODY];
        yield 'blank'      => ['   ', ApiErrorCode::EMPTY_BODY];
        yield 'not json'   => ['{"title": ', ApiErrorCode::MALFORMED_JSON];
        yield 'list'       => ['[1, 2]', ApiErrorCode::INVALID_BODY];
        yield 'string'     => ['"x"', ApiErrorCode::INVALID_BODY];
        yield 'json null'  => ['null', ApiErrorCode::INVALID_BODY];
    }

    #[DataProvider('malformedBodies')]
    public function test_rejects_bodies_that_are_not_a_json_object(string $body, string $errorCode): void
    {
        $this->assertPayloadError($errorCode, [], fn () => $this->json($body, []));
    }

    public function test_only_json_is_accepted(): void
    {
        $request = Request::create('/api/v1/tasks', 'POST', server: ['CONTENT_TYPE' => 'application/xml'], content: '<task/>');

        $this->expectException(UnsupportedMediaTypeHttpException::class);
        Payload::fromJsonBody($request, []);
    }

    public function test_the_query_string_is_untyped_so_values_are_parsed(): void
    {
        $query = Payload::fromQuery(Request::create('/api/v1/tasks?page=2&limit=abc&ratio=0.5&mine=true&status=done'), ['page', 'limit', 'ratio', 'mine', 'status', 'other']);

        $this->assertSame(2, $query->int('page'));
        $this->assertSame(0.5, $query->float('ratio'));
        $this->assertTrue($query->bool('mine'));
        $this->assertSame('done', $query->string('status'));
        $this->assertNull($query->optionalInt('other'));
        $this->assertPayloadError(ApiErrorCode::INVALID_TYPE, ['limit'], static fn () => $query->optionalInt('limit'));
    }

    /**
     * @param list<string> $allowed
     */
    private function json(string $body, array $allowed): Payload
    {
        return Payload::fromJsonBody(Request::create('/api/v1/tasks', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body), $allowed);
    }

    /**
     * @param list<string> $fields
     */
    private function assertPayloadError(string $errorCode, array $fields, callable $action): void
    {
        try {
            $action();
        } catch (InvalidPayloadException $exception) {
            $this->assertSame($errorCode, $exception->errorCode);
            $this->assertSame($fields, $exception->fields);

            return;
        }

        $this->fail(\sprintf('Expected %s, nothing was thrown.', $errorCode));
    }
}
