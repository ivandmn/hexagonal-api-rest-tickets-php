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

        $memory      = $status['memory_usage'];
        $stats       = $status['opcache_statistics'];
        $strings     = $status['interned_strings_usage'] ?? null;
        $jit         = $status['jit'] ?? null;
        $totalMemory = $memory['used_memory'] + $memory['free_memory'] + $memory['wasted_memory'];

        $blocks = [
            Block::kv([
                'Enabled'          => Block::flag($status['opcache_enabled']),
                'Cache full'       => Block::flag($status['cache_full'], 'yes', 'no'),
                'Restart pending'  => Block::flag($status['restart_pending'], 'yes', 'no'),
                'Hit rate'         => Block::badge(\round($stats['opcache_hit_rate'], 2) . ' %', $stats['opcache_hit_rate'] > 90 ? 'ok' : 'warn'),
                'Hits / misses'    => \sprintf('%s / %s', \number_format($stats['hits']), \number_format($stats['misses'])),
                'Cached scripts'   => $stats['num_cached_scripts'],
                'Cached keys'      => \sprintf('%d of %d', $stats['num_cached_keys'], $stats['max_cached_keys']),
                'Wasted memory'    => \sprintf('%s (%.2f %%)', Format::bytes($memory['wasted_memory']), $memory['current_wasted_percentage']),
                'OOM restarts'     => $stats['oom_restarts'],
                'Hash restarts'    => $stats['hash_restarts'],
                'Manual restarts'  => $stats['manual_restarts'],
                'Started'          => Format::date($stats['start_time']),
                'Last restart'     => Format::date($stats['last_restart_time']) ?? 'never',
            ], 'Status'),
            Block::meter('Shared memory', $memory['used_memory'], $totalMemory),
        ];

        if (null !== $strings) {
            $blocks[] = Block::meter('Interned strings buffer', $strings['used_memory'], $strings['buffer_size']);
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

            if (($jit['buffer_size'] ?? 0) > 0) {
                $blocks[] = Block::meter('JIT buffer', $jit['buffer_size'] - $jit['buffer_free'], $jit['buffer_size']);
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
            'summary' => \sprintf('%s · hit rate %.1f %% · JIT %s', $status['opcache_enabled'] ? 'enabled' : 'disabled', $stats['opcache_hit_rate'], ($jit['on'] ?? false) ? 'on' : 'off'),
            'blocks'  => $blocks,
        ];
    }
}
