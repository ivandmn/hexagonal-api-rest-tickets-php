<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

final class Format
{
    private const ERROR_LEVELS = [
        'E_ERROR'             => \E_ERROR,
        'E_WARNING'           => \E_WARNING,
        'E_PARSE'             => \E_PARSE,
        'E_NOTICE'            => \E_NOTICE,
        'E_CORE_ERROR'        => \E_CORE_ERROR,
        'E_CORE_WARNING'      => \E_CORE_WARNING,
        'E_COMPILE_ERROR'     => \E_COMPILE_ERROR,
        'E_COMPILE_WARNING'   => \E_COMPILE_WARNING,
        'E_USER_ERROR'        => \E_USER_ERROR,
        'E_USER_WARNING'      => \E_USER_WARNING,
        'E_USER_NOTICE'       => \E_USER_NOTICE,
        'E_RECOVERABLE_ERROR' => \E_RECOVERABLE_ERROR,
        'E_DEPRECATED'        => \E_DEPRECATED,
        'E_USER_DEPRECATED'   => \E_USER_DEPRECATED,
    ];

    public static function bytes(float|int $bytes, int $precision = 1): string
    {
        if ($bytes < 1024) {
            return \sprintf('%d B', $bytes);
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $power = \min(\count($units), (int) \floor(\log($bytes, 1024)));

        return \sprintf('%s %s', \round($bytes / 1024 ** $power, $precision), $units[$power - 1]);
    }

    /**
     * Parses php.ini shorthand sizes ("512M", "-1"...) into bytes; -1 means unlimited.
     */
    public static function iniBytes(false|string $value): int
    {
        if (false === $value || '' === $value) {
            return 0;
        }

        $value  = \trim($value);
        $number = (int) $value;

        return match (\strtolower(\substr($value, -1))) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    public static function duration(float|int $seconds): string
    {
        $seconds = (int) $seconds;
        $parts   = [];

        foreach (['d' => 86400, 'h' => 3600, 'm' => 60] as $unit => $size) {
            if ($seconds >= $size) {
                $parts[] = \intdiv($seconds, $size) . $unit;
                $seconds %= $size;
            }
        }

        if ($seconds > 0 || [] === $parts) {
            $parts[] = $seconds . 's';
        }

        return \implode(' ', $parts);
    }

    public static function date(float|int|null $timestamp): ?string
    {
        return $timestamp ? new \DateTimeImmutable('@' . (int) $timestamp)->setTimezone(new \DateTimeZone(\date_default_timezone_get()))->format('Y-m-d H:i:s T') : null;
    }

    /**
     * Turns an error_reporting bitmask into "E_ALL & ~E_DEPRECATED" style.
     */
    public static function errorLevel(int $level): string
    {
        if (\E_ALL === $level) {
            return 'E_ALL';
        }

        $missing = \array_keys(\array_filter(self::ERROR_LEVELS, static fn (int $bit): bool => 0 === ($level & $bit)));
        $enabled = \array_keys(\array_filter(self::ERROR_LEVELS, static fn (int $bit): bool => 0 !== ($level & $bit)));

        if (\count($missing) <= \count($enabled)) {
            return \implode(' & ', ['E_ALL', ...\array_map(static fn (string $name): string => '~' . $name, $missing)]);
        }

        return [] === $enabled ? '0' : \implode(' | ', $enabled);
    }

    public static function shortClass(string $class): string
    {
        $position = \strrpos($class, '\\');

        return false === $position ? $class : \substr($class, $position + 1);
    }
}
