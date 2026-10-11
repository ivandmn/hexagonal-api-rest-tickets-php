<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 70)]
final readonly class OpcacheSection implements InfoSectionInterface
{
    public function id(): string
    {
        return 'opcache';
    }

    public function title(): string
    {
        return 'OPcache & JIT';
    }

    public function icon(): string
    {
        return '⚡';
    }

    public function collect(): array
    {
        if (!\function_exists('opcache_get_status')) {
            return ['summary' => 'not loaded', 'blocks' => [Block::note('The OPcache extension is not loaded.', 'warn')]];
        }

        $status        = @\opcache_get_status(false);
        $configuration = @\opcache_get_configuration();

        if (false === $status) {
            return ['summary' => 'disabled', 'blocks' => [Block::note('OPcache is loaded but disabled for this SAPI (or opcache.restrict_api blocks the status API).', 'warn')]];
        }

        $enabled     = (bool) ($status['opcache_enabled'] ?? false);
        $memory      = self::array($status, 'memory_usage');
        $stats       = self::array($status, 'opcache_statistics');
        $strings     = $status['interned_strings_usage'] ?? null;
        $jit         = $status['jit'] ?? null;
        $hitRate     = self::float($stats, 'opcache_hit_rate');
        $totalMemory = self::int($memory, 'used_memory') + self::int($memory, 'free_memory') + self::int($memory, 'wasted_memory');

        $blocks = [
            Block::kv([
                'Enabled'          => Block::flag($enabled),
                'Cache full'       => Block::flag((bool) ($status['cache_full'] ?? false), 'yes', 'no'),
                'Restart pending'  => Block::flag((bool) ($status['restart_pending'] ?? false), 'yes', 'no'),
                'Hit rate'         => Block::badge(\round($hitRate, 2) . ' %', $hitRate > 90 ? 'ok' : 'warn'),
                'Hits / misses'    => \sprintf('%s / %s', \number_format(self::int($stats, 'hits')), \number_format(self::int($stats, 'misses'))),
                'Cached scripts'   => self::int($stats, 'num_cached_scripts'),
                'Cached keys'      => \sprintf('%d of %d', self::int($stats, 'num_cached_keys'), self::int($stats, 'max_cached_keys')),
                'Wasted memory'    => \sprintf('%s (%.2f %%)', Format::bytes(self::int($memory, 'wasted_memory')), self::float($memory, 'current_wasted_percentage')),
                'OOM restarts'     => self::int($stats, 'oom_restarts'),
                'Hash restarts'    => self::int($stats, 'hash_restarts'),
                'Manual restarts'  => self::int($stats, 'manual_restarts'),
                'Started'          => Format::date(self::int($stats, 'start_time')),
                'Last restart'     => Format::date(self::int($stats, 'last_restart_time')) ?? 'never',
            ], 'Status'),
            Block::meter('Shared memory', self::int($memory, 'used_memory'), $totalMemory),
        ];

        if (\is_array($strings)) {
            $blocks[] = Block::meter('Interned strings buffer', self::int($strings, 'used_memory'), self::int($strings, 'buffer_size'));
        }

        if (\is_array($jit)) {
            $blocks[] = Block::kv([
                'Enabled'      => Block::flag((bool) ($jit['enabled'] ?? false)),
                'Active'       => Block::flag((bool) ($jit['on'] ?? false)),
                'Mode (jit)'   => (string) \ini_get('opcache.jit'),
                'Kind'         => $jit['kind'] ?? null,
                'Opt level'    => $jit['opt_level'] ?? null,
                'Opt flags'    => $jit['opt_flags'] ?? null,
            ], 'JIT');

            $jitBuffer = self::int($jit, 'buffer_size');
            if ($jitBuffer > 0) {
                $blocks[] = Block::meter('JIT buffer', $jitBuffer - self::int($jit, 'buffer_free'), $jitBuffer);
            }
        }

        if (\is_array($configuration)) {
            $directives = $configuration['directives'];
            \ksort($directives);
            $blocks[] = Block::items([
                Block::item('Directives', [Block::kv($directives)], (string) \count($directives)),
                Block::item('Version', [Block::kv($configuration['version'])]),
            ]);
        }

        return [
            'summary' => \sprintf('%s · hit rate %.1f %% · JIT %s', $enabled ? 'enabled' : 'disabled', $hitRate, \is_array($jit) && ($jit['on'] ?? false) ? 'on' : 'off'),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : [];
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : 0;
    }

    /**
     * @param array<mixed> $data
     */
    private static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }
}
