<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Exception;

use App\Domain\Exception\DomainException;
use App\UI\Http\Exception\ApiErrorCode;
use App\UI\Http\Exception\ErrorCodeMapper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the error-code contract: every code declared in src/ (domain exceptions and the API's
 * own codes) is mapped in config/packages/error_mapping.yaml, nothing else is, codes are unique
 * and follow the naming convention. Uses the real container, so the YAML is validated too.
 */
final class ErrorCodeCoverageTest extends KernelTestCase
{
    private const FORMAT = '/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)+$/';

    /** @var array<string, list<string>>|null code => classes declaring it */
    private static ?array $declared = null;

    public function test_the_mapper_boots_from_the_yaml(): void
    {
        $this->assertNotEmpty($this->mapper()->mappedCodes());
    }

    public function test_every_declared_code_is_mapped(): void
    {
        $missing = \array_diff(\array_keys(self::declaredCodes()), $this->mapper()->mappedCodes());

        $this->assertSame([], \array_values($missing), 'Add these codes to config/packages/error_mapping.yaml');
    }

    public function test_every_mapped_code_is_declared(): void
    {
        $orphans = \array_diff($this->mapper()->mappedCodes(), \array_keys(self::declaredCodes()));

        $this->assertSame([], \array_values($orphans), 'These mapped codes are not declared anywhere in src/');
    }

    public function test_codes_are_unique(): void
    {
        $duplicated = \array_filter(self::declaredCodes(), static fn (array $classes): bool => \count(\array_unique($classes)) > 1);

        $this->assertSame([], $duplicated, 'An error code must mean one thing only');
    }

    public function test_codes_follow_the_naming_convention(): void
    {
        foreach (\array_keys(self::declaredCodes()) as $code) {
            $this->assertMatchesRegularExpression(self::FORMAT, $code);
        }
    }

    public function test_domain_exceptions_were_found(): void
    {
        // Sanity check of the scan itself, so the tests above cannot pass vacuously.
        $this->assertArrayHasKey('TASK_NOT_FOUND', self::declaredCodes());
        $this->assertArrayHasKey('VALUE_REQUIRED', self::declaredCodes());
        $this->assertArrayHasKey(ApiErrorCode::VALIDATION_ERROR, self::declaredCodes());
    }

    private function mapper(): ErrorCodeMapper
    {
        $mapper = static::getContainer()->get(ErrorCodeMapper::class);
        $this->assertInstanceOf(ErrorCodeMapper::class, $mapper);

        return $mapper;
    }

    /**
     * String constants of every DomainException subclass and of ApiErrorCode.
     *
     * @return array<string, list<string>>
     */
    private static function declaredCodes(): array
    {
        if (null !== self::$declared) {
            return self::$declared;
        }

        $src   = \dirname(__DIR__, 5) . '/src';
        $codes = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }

            $class = 'App\\' . \str_replace(['/', '.php'], ['\\', ''], \substr($file->getPathname(), \strlen($src) + 1));
            if (!\class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if (!$reflection->isSubclassOf(DomainException::class) && ApiErrorCode::class !== $class) {
                continue;
            }

            foreach ($reflection->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() === $class && \is_string($constant->getValue())) {
                    $codes[$constant->getValue()][] = $class;
                }
            }
        }

        return self::$declared = $codes;
    }
}
