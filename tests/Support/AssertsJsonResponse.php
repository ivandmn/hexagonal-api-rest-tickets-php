<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Assertions for the API success contract: `{"status": "success", "data": ..., "meta"?: ...}`.
 */
trait AssertsJsonResponse
{
    /**
     * @return array<array-key, mixed> the decoded body, for extra assertions
     */
    private function assertJsonSuccess(KernelBrowser $client, int $status): array
    {
        $response = $client->getResponse();

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('application/json', $response->headers->get('Content-Type'));

        $json = \json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($json);
        self::assertSame('success', $json['status']);
        self::assertArrayHasKey('data', $json);

        return $json;
    }

    /**
     * Every key at every level is snake_case (the API's naming convention).
     *
     * @param array<array-key, mixed> $data
     */
    private function assertSnakeCaseKeys(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            if (\is_string($key)) {
                self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key, \sprintf('Key "%s%s" is not snake_case', $path, $key));
            }
            if (\is_array($value)) {
                $this->assertSnakeCaseKeys($value, $path . $key . '.');
            }
        }
    }
}
