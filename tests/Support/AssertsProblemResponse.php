<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Assertions for the API error contract (Problem Details built by ApiExceptionListener).
 */
trait AssertsProblemResponse
{
    /**
     * @param array<string, string>|null $fieldErrors expected `errors[]` as field => code (exact set), or null to skip
     *
     * @return array<array-key, mixed> the decoded problem, for extra assertions
     */
    private function assertProblem(KernelBrowser $client, int $status, string $type, ?array $fieldErrors = null): array
    {
        $response = $client->getResponse();

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $problem = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($problem);

        self::assertSame($type, $problem['type']);
        self::assertSame($status, $problem['status']);
        self::assertIsString($problem['title']);
        self::assertNotSame('', $problem['title']);
        self::assertIsString($problem['trace_id']);
        self::assertMatchesRegularExpression('/^\S+$/', $problem['trace_id']);

        if (null !== $fieldErrors) {
            $actual = [];
            $errors = $problem['errors'] ?? [];
            self::assertIsArray($errors);
            foreach ($errors as $error) {
                self::assertIsArray($error);
                self::assertIsString($error['field']);
                $actual[$error['field']] = $error['code'];
            }
            self::assertSame($fieldErrors, $actual, 'Unexpected errors[] (field => code)');
        }

        return $problem;
    }
}
