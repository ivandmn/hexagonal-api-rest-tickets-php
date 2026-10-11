<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\Http\Info;

use App\UI\Http\Info\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    /**
     * @return iterable<array{int, string}>
     */
    public static function bytes(): iterable
    {
        yield [0, '0 B'];
        yield [1023, '1023 B'];
        yield [1024, '1 KB'];
        yield [1536, '1.5 KB'];
        yield [512 * 1024 ** 2, '512 MB'];
        yield [3 * 1024 ** 3, '3 GB'];
    }

    #[DataProvider('bytes')]
    public function test_formats_bytes(int $bytes, string $expected): void
    {
        $this->assertSame($expected, Format::bytes($bytes));
    }

    /**
     * @return iterable<array{false|string, int}>
     */
    public static function iniSizes(): iterable
    {
        yield ['512M', 512 * 1024 ** 2];
        yield ['1G', 1024 ** 3];
        yield ['64k', 64 * 1024];
        yield ['4096', 4096];
        yield ['-1', -1];
        yield [false, 0];
    }

    #[DataProvider('iniSizes')]
    public function test_parses_ini_sizes(false|string $value, int $expected): void
    {
        $this->assertSame($expected, Format::iniBytes($value));
    }

    /**
     * @return iterable<array{int, string}>
     */
    public static function durations(): iterable
    {
        yield [0, '0s'];
        yield [59, '59s'];
        yield [60, '1m'];
        yield [3661, '1h 1m 1s'];
        yield [90000, '1d 1h'];
    }

    #[DataProvider('durations')]
    public function test_formats_durations(int $seconds, string $expected): void
    {
        $this->assertSame($expected, Format::duration($seconds));
    }

    public function test_describes_error_reporting_levels(): void
    {
        $this->assertSame('E_ALL', Format::errorLevel(\E_ALL));
        $this->assertSame('E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED', Format::errorLevel(\E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED));
        $this->assertSame('E_ERROR | E_WARNING', Format::errorLevel(\E_ERROR | \E_WARNING));
        $this->assertSame('0', Format::errorLevel(0));
    }

    public function test_shortens_class_names(): void
    {
        $this->assertSame('PostgreSQLPlatform', Format::shortClass('Doctrine\DBAL\Platforms\PostgreSQLPlatform'));
        $this->assertSame('stdClass', Format::shortClass('stdClass'));
    }
}
