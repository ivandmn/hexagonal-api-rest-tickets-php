<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the deploy and the API clients rely on before anything else: the health check answers and
 * the OpenAPI document describes every endpoint.
 */
final class SmokeTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function healthChecks(): iterable
    {
        yield 'health' => ['/health'];
        yield 'status' => ['/status'];
        yield 'root'   => ['/'];
    }

    #[DataProvider('healthChecks')]
    public function test_health_check_answers_ok_with_no_body(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertSame('', (string) $client->getResponse()->getContent());
    }

    public function test_openapi_document_describes_every_endpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs.json');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $doc = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertIsArray($doc);
        $this->assertIsString($doc['openapi']);
        $this->assertStringStartsWith('3.', $doc['openapi']);
        $this->assertIsArray($doc['paths']);

        $operations = [];
        foreach ($doc['paths'] as $path => $methods) {
            $this->assertIsArray($methods);
            foreach (\array_keys($methods) as $method) {
                $operations[] = \strtoupper((string) $method) . ' ' . $path;
            }
        }

        $this->assertEqualsCanonicalizing([
            'GET /api/v1/tasks',
            'POST /api/v1/tasks',
            'GET /api/v1/tasks/{id}',
            'PATCH /api/v1/tasks/{id}',
            'DELETE /api/v1/tasks/{id}',
            'PATCH /api/v1/tasks/{id}/assign',
            'DELETE /api/v1/tasks/{id}/assign',
            'GET /api/v1/users',
            'POST /api/v1/users',
            'GET /api/v1/users/{id}',
            'PATCH /api/v1/users/{id}',
            'DELETE /api/v1/users/{id}',
        ], $operations);
    }

    public function test_every_body_is_documented(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs.json');

        $doc = \json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->assertIsArray($doc);

        $bodies = [
            ['/api/v1/tasks', 'post', ['title', 'description', 'priority', 'due_date']],
            ['/api/v1/tasks/{id}', 'patch', ['title', 'description', 'status', 'priority', 'due_date']],
            ['/api/v1/tasks/{id}/assign', 'patch', ['user_id']],
            ['/api/v1/users', 'post', ['email', 'name']],
        ];

        foreach ($bodies as [$path, $method, $fields]) {
            $schema = $doc['paths'][$path][$method]['requestBody']['content']['application/json']['schema'] ?? null;
            $this->assertIsArray($schema, "{$method} {$path}");
            $this->assertIsArray($schema['properties'] ?? null, "{$method} {$path}");
            $this->assertSame($fields, \array_keys($schema['properties']), "{$method} {$path}");
        }
    }

    public function test_the_documentation_page_renders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
    }
}
