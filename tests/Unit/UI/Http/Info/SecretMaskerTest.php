<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Info;

use App\UI\Http\Info\SecretMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretMaskerTest extends TestCase
{
    private SecretMasker $masker;

    protected function setUp(): void
    {
        $this->masker = new SecretMasker();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretKeys(): iterable
    {
        yield 'APP_SECRET' => ['APP_SECRET'];
        yield 'DB_PASSWORD' => ['DB_PASSWORD'];
        yield 'kernel.secret' => ['kernel.secret'];
        yield 'password' => ['password'];
        yield 'API_KEY' => ['API_KEY'];
        yield 'GITHUB_TOKEN' => ['GITHUB_TOKEN'];
        yield 'authorization header' => ['authorization'];
        yield 'cookie header' => ['cookie'];
        yield 'jwt-private-key' => ['jwt-private-key'];
    }

    #[DataProvider('secretKeys')]
    public function test_masks_values_of_secret_looking_keys(string $key): void
    {
        $this->assertSame(SecretMasker::MASK, $this->masker->mask($key, 'super-secret-value'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessKeys(): iterable
    {
        yield 'APP_ENV' => ['APP_ENV'];
        yield 'SERVER_NAME' => ['SERVER_NAME'];
        yield 'PHP_IDE_CONFIG' => ['PHP_IDE_CONFIG'];
        yield 'DB_HOST' => ['DB_HOST'];
        yield 'monkey (contains "key" inside a word)' => ['monkey'];
    }

    #[DataProvider('harmlessKeys')]
    public function test_keeps_values_of_harmless_keys(string $key): void
    {
        $this->assertSame('visible', $this->masker->mask($key, 'visible'));
    }

    public function test_masks_only_the_password_of_a_url(): void
    {
        $this->assertSame(
            'postgresql://admin:' . SecretMasker::MASK . '@db.example.com:5432/app?serverVersion=18',
            $this->masker->mask('DATABASE_URL', 'postgresql://admin:s3cret@db.example.com:5432/app?serverVersion=18'),
        );
    }

    public function test_masks_a_url_password_containing_at_signs_and_colons(): void
    {
        $masked = $this->masker->maskUrl('postgresql://admin:p@ss:w@rd*x@10.0.0.1:5432/app');

        $this->assertSame('postgresql://admin:' . SecretMasker::MASK . '@10.0.0.1:5432/app', $masked);
        $this->assertStringNotContainsString('rd*x', $masked);
    }

    public function test_leaves_urls_without_credentials_untouched(): void
    {
        $this->assertSame('redis://redis:6379', $this->masker->maskUrl('redis://redis:6379'));
        $this->assertSame('https://user@example.com/path', $this->masker->maskUrl('https://user@example.com/path'));
    }

    public function test_masks_nested_arrays(): void
    {
        $masked = $this->masker->maskArray([
            'host'     => 'db',
            'password' => 'hunter2',
            'url'      => 'mysql://root:hunter2@db/app',
            'options'  => ['token' => 'abc', 'timeout' => 5],
        ]);

        $this->assertSame('db', $masked['host']);
        $this->assertSame(SecretMasker::MASK, $masked['password']);
        $this->assertSame('mysql://root:' . SecretMasker::MASK . '@db/app', $masked['url']);
        $this->assertSame(['token' => SecretMasker::MASK, 'timeout' => 5], $masked['options']);
    }

    public function test_keeps_empty_and_boolean_values(): void
    {
        $this->assertNull($this->masker->mask('APP_SECRET', null));
        $this->assertSame('', $this->masker->mask('APP_SECRET', ''));
        $this->assertTrue($this->masker->mask('token', true));
    }
}
