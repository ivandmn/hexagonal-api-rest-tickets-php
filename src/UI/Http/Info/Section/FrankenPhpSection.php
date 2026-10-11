<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsTaggedItem(priority: 60)]
final readonly class FrankenPhpSection implements InfoSectionInterface
{
    /** Caddy admin API, only reachable from inside the container. */
    private const ADMIN_API = 'http://localhost:2019';

    private const FUNCTIONS = [
        'frankenphp_handle_request', 'frankenphp_finish_request', 'frankenphp_request_headers',
        'frankenphp_response_headers', 'frankenphp_log', 'mercure_publish', 'headers_send',
    ];

    private const METRICS = [
        'process_resident_memory_bytes' => 'Resident memory',
        'process_cpu_seconds_total'     => 'CPU time',
        'process_open_fds'              => 'Open file descriptors',
        'go_goroutines'                 => 'Goroutines',
        'go_threads'                    => 'OS threads',
        'go_memstats_heap_inuse_bytes'  => 'Go heap in use',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'frankenphp';
    }

    public function title(): string
    {
        return 'FrankenPHP & Caddy';
    }

    public function icon(): string
    {
        return '🧟';
    }

    public function collect(): array
    {
        $server = $this->requestStack->getMainRequest()?->server;

        if ('frankenphp' !== \PHP_SAPI) {
            return ['summary' => \PHP_SAPI, 'blocks' => [Block::note(\sprintf('Not running under FrankenPHP (SAPI: %s).', \PHP_SAPI), 'muted')]];
        }

        $worker = (bool) $server?->get('FRANKENPHP_WORKER');

        $blocks = [
            Block::kv([
                'Server software'          => $server?->get('SERVER_SOFTWARE'),
                'Worker mode'              => Block::flag($worker, 'worker', 'classic'),
                'Runtime'                  => $server?->get('APP_RUNTIME') ?? 'Symfony\Component\Runtime\SymfonyRuntime',
                'Server name'              => \getenv('SERVER_NAME') ?: null,
                'FRANKENPHP_CONFIG'        => \getenv('FRANKENPHP_CONFIG') ?: null,
                'FRANKENPHP_WORKER_CONFIG' => \getenv('FRANKENPHP_WORKER_CONFIG') ?: null,
                'CADDY_GLOBAL_OPTIONS'     => \getenv('CADDY_GLOBAL_OPTIONS') ?: null,
                'Document root'            => $server?->get('DOCUMENT_ROOT'),
                'Script'                   => $server?->get('SCRIPT_FILENAME'),
            ], 'Runtime'),
            Block::tags(\array_map(
                static fn (string $function): array => ['label' => $function, 'tone' => \function_exists($function) ? 'ok' : 'muted'],
                self::FUNCTIONS,
            ), 'PHP functions (grey = unavailable)'),
        ];

        $config = $this->fetchJson('/config/');
        if (null === $config) {
            $blocks[] = Block::note('Caddy admin API not reachable at ' . self::ADMIN_API . '.', 'warn');
        } else {
            $config = $this->masker->maskArray($config);
            $blocks = [...$blocks, ...$this->describeConfig($config)];
        }

        $metricsBody = $this->fetch('/metrics');
        $metrics     = null !== $metricsBody ? $this->parseMetrics($metricsBody) : [];
        if ([] !== $metrics) {
            $rows = [];
            foreach (self::METRICS as $name => $label) {
                if (isset($metrics[$name])) {
                    $rows[$label] = \str_ends_with($name, '_bytes') ? Format::bytes($metrics[$name]) : ('process_cpu_seconds_total' === $name ? Format::duration($metrics[$name]) : (string) $metrics[$name]);
                }
            }

            $frankenphp = \array_filter($metrics, static fn (string $name): bool => \str_starts_with($name, 'frankenphp_'), \ARRAY_FILTER_USE_KEY);

            $build = [];
            if (1 === \preg_match('~^go_build_info\{[^}]*path="([^"]+)"[^}]*version="([^"]+)"~m', (string) $metricsBody, $match)) {
                $build['Build'] = $match[1] . ' @ ' . $match[2];
            }
            if (1 === \preg_match('~^go_info\{version="([^"]+)"\}~m', (string) $metricsBody, $match)) {
                $build['Go'] = $match[1];
            }

            $blocks[] = Block::kv($build + $rows + $frankenphp, 'Binary & process metrics');

            if ([] === $frankenphp) {
                $blocks[] = Block::note('FrankenPHP worker metrics (busy/total workers, queue depth...) are off: add the `metrics` global option to the Caddyfile to enable them.', 'muted');
            }
        }

        return [
            'summary' => \sprintf('%s · %s', \preg_replace('~\s.*$~', '', (string) $server?->get('SERVER_SOFTWARE')) ?: 'FrankenPHP', $worker ? 'worker mode' : 'classic mode'),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    private function describeConfig(array $config): array
    {
        $blocks = [];

        $workers = $config['apps']['frankenphp']['workers'] ?? [];
        if ([] !== $workers) {
            $blocks[] = Block::table(['File', 'Instances', 'Watch'], \array_values(\array_map(
                static fn (array $worker): array => [$worker['file_name'] ?? '?', $worker['num'] ?? 'auto (2 × CPU)', $worker['watch'] ?? []],
                $workers,
            )), 'Workers');
        }

        $servers = [];
        foreach ($config['apps']['http']['servers'] ?? [] as $name => $server) {
            $servers[] = [
                $name,
                $server['listen'] ?? [],
                \count($server['routes'] ?? []),
                $server['trusted_proxies']['ranges'] ?? [],
            ];
        }
        if ([] !== $servers) {
            $blocks[] = Block::table(['Server', 'Listen', 'Routes', 'Trusted proxies'], $servers, 'HTTP servers');
        }

        $blocks[] = Block::items([
            Block::item('Live Caddy JSON config', [Block::code((string) \json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES))], 'admin API'),
        ]);

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $path): ?array
    {
        $body = $this->fetch($path);
        $data = null !== $body ? \json_decode($body, true) : null;

        return \is_array($data) ? $data : null;
    }

    /**
     * @return array<string, float> Prometheus samples by family
     */
    private function parseMetrics(string $body): array
    {
        $metrics = [];
        foreach (\explode("\n", $body) as $line) {
            if (1 === \preg_match('~^([a-z_:][a-z0-9_:]*)(\{[^}]*\})?\s+([0-9.eE+\-]+)$~i', \trim($line), $match)) {
                // Labelled series are summed into their family (e.g. per-worker gauges).
                $metrics[$match[1]] = ($metrics[$match[1]] ?? 0) + (float) $match[3];
            }
        }

        return $metrics;
    }

    private function fetch(string $path): ?string
    {
        $context = \stream_context_create(['http' => ['timeout' => 1.0, 'ignore_errors' => true]]);
        $body    = @\file_get_contents(self::ADMIN_API . $path, false, $context);

        return false === $body ? null : $body;
    }
}
