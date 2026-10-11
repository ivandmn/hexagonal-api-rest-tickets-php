<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsTaggedItem(priority: 100)]
final readonly class OverviewSection implements InfoSectionInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private RequestStack $requestStack,
    ) {
    }

    public function id(): string
    {
        return 'overview';
    }

    public function title(): string
    {
        return 'Overview';
    }

    public function icon(): string
    {
        return '🧭';
    }

    public function collect(): array
    {
        $server      = $this->requestStack->getMainRequest()?->server;
        $memoryLimit = Format::iniBytes(\ini_get('memory_limit'));

        $stack = [
            'Environment'   => Block::badge($this->kernel->getEnvironment(), 'prod' === $this->kernel->getEnvironment() ? 'danger' : 'info'),
            'Debug'         => Block::flag($this->kernel->isDebug()),
            'PHP'           => \PHP_VERSION,
            'SAPI'          => \PHP_SAPI,
            'Server'        => $server?->get('SERVER_SOFTWARE'),
            'Worker mode'   => Block::flag((bool) $server?->get('FRANKENPHP_WORKER'), 'yes', 'no'),
            'Symfony'       => Kernel::VERSION,
            'Doctrine ORM'  => InstalledVersions::getPrettyVersion('doctrine/orm'),
            'Xdebug'        => \extension_loaded('xdebug') ? Block::badge((string) \ini_get('xdebug.mode'), 'warn') : Block::badge('not loaded', 'muted'),
            'OPcache'       => Block::flag(\function_exists('opcache_get_status') && false !== @\opcache_get_status(false)),
        ];

        $system = [
            'Hostname'       => \gethostname() ?: null,
            'OS'             => \php_uname('s') . ' ' . \php_uname('r') . ' (' . \php_uname('m') . ')',
            'Distribution'   => $this->distribution(),
            'CPUs'           => $this->cpuCount(),
            'Load average'   => \function_exists('sys_getloadavg') && \is_array($load = \sys_getloadavg()) ? \implode(' / ', \array_map(static fn (float $l): string => \number_format($l, 2), $load)) : null,
            'System uptime'  => \is_readable('/proc/uptime') ? Format::duration((float) \file_get_contents('/proc/uptime')) : null,
            'PID'            => \getmypid(),
            'User'           => \function_exists('posix_getpwuid') && \function_exists('posix_geteuid') ? (\posix_getpwuid(\posix_geteuid())['name'] ?? null) : \get_current_user(),
            'Timezone'       => \date_default_timezone_get(),
            'Server time'    => new \DateTimeImmutable()->format('Y-m-d H:i:s T'),
        ];

        $blocks = [
            Block::kv($stack, 'Stack'),
            Block::kv($system, 'Host'),
            Block::meter('PHP memory (this request vs memory_limit)', \memory_get_usage(true), $memoryLimit > 0 ? $memoryLimit : \memory_get_usage(true)),
            Block::meter('PHP peak memory', \memory_get_peak_usage(true), $memoryLimit > 0 ? $memoryLimit : \memory_get_peak_usage(true)),
        ];

        if (null !== $cgroup = $this->cgroupMemory()) {
            $blocks[] = Block::meter('Container memory (cgroup)', $cgroup['used'], $cgroup['limit']);
        }

        $projectDir = $this->kernel->getProjectDir();
        $total      = @\disk_total_space($projectDir);
        $free       = @\disk_free_space($projectDir);
        if (false !== $total && false !== $free) {
            $blocks[] = Block::meter('Disk (project volume)', $total - $free, $total);
        }

        return [
            'summary' => \sprintf('%s · PHP %s · Symfony %s', $this->kernel->getEnvironment(), \PHP_VERSION, Kernel::VERSION),
            'blocks'  => $blocks,
        ];
    }

    private function distribution(): ?string
    {
        if (!\is_readable('/etc/os-release')) {
            return null;
        }

        $release = \parse_ini_file('/etc/os-release') ?: [];
        $name    = $release['PRETTY_NAME'] ?? null;

        return \is_string($name) ? $name : null;
    }

    private function cpuCount(): ?int
    {
        if (\is_readable('/proc/cpuinfo')) {
            return \substr_count((string) \file_get_contents('/proc/cpuinfo'), 'processor') ?: null;
        }

        return null;
    }

    /**
     * @return array{used: int, limit: int}|null
     */
    private function cgroupMemory(): ?array
    {
        $limit = @\file_get_contents('/sys/fs/cgroup/memory.max');
        $used  = @\file_get_contents('/sys/fs/cgroup/memory.current');

        if (false === $limit || false === $used || 'max' === \trim($limit)) {
            return null;
        }

        return ['used' => (int) $used, 'limit' => (int) $limit];
    }
}
