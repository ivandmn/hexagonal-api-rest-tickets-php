<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsTaggedItem(priority: 50)]
final readonly class SymfonySection implements InfoSectionInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private ParameterBagInterface $parameters,
        #[Autowire(service: 'service_container')]
        private Container $container,
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'symfony';
    }

    public function title(): string
    {
        return 'Symfony';
    }

    public function icon(): string
    {
        return '🎼';
    }

    public function collect(): array
    {
        $endOfMaintenance = \DateTimeImmutable::createFromFormat('!m/Y', Kernel::END_OF_MAINTENANCE);
        $endOfLife        = \DateTimeImmutable::createFromFormat('!m/Y', Kernel::END_OF_LIFE);
        $now              = new \DateTimeImmutable();

        $support = match (true) {
            false !== $endOfLife && $now > $endOfLife               => Block::badge('end of life', 'danger'),
            false !== $endOfMaintenance && $now > $endOfMaintenance => Block::badge('security fixes only', 'warn'),
            default                                                 => Block::badge('maintained', 'ok'),
        };

        $buildTime = $this->parameters->has('container.build_time') ? (int) $this->parameters->get('container.build_time') : null;

        $blocks = [
            Block::kv([
                'Version'            => Kernel::VERSION,
                'Support'            => $support,
                'End of maintenance' => Kernel::END_OF_MAINTENANCE,
                'End of life'        => Kernel::END_OF_LIFE,
                'Environment'        => $this->kernel->getEnvironment(),
                'Debug'              => Block::flag($this->kernel->isDebug()),
                'Kernel'             => $this->kernel::class,
                'Container'          => $this->container::class,
                'Container built'    => null !== $buildTime ? Format::date($buildTime) . ' (' . Format::duration(\time() - $buildTime) . ' ago)' : null,
                'Services'           => \count($this->container->getServiceIds()),
                'Parameters'         => \count($this->parameters->all()),
                'Charset'            => $this->kernel->getCharset(),
                'Default locale'     => $this->parameter('kernel.default_locale'),
                'Secret'             => $this->parameters->has('kernel.secret') ? Block::badge('set', 'ok') : Block::badge('missing', 'danger'),
            ], 'Kernel'),
            Block::kv([
                'Project'  => $this->kernel->getProjectDir(),
                'Cache'    => $this->kernel->getCacheDir(),
                'Build'    => $this->kernel->getBuildDir(),
                'Logs'     => $this->kernel->getLogDir(),
                'Share'    => \method_exists($this->kernel, 'getShareDir') ? $this->kernel->getShareDir() : null,
            ], 'Directories'),
            $this->bundles(),
        ];

        $kernelParameters = [];
        $allParameters    = [];
        $parameters       = $this->parameters->all();
        \ksort($parameters);
        foreach ($parameters as $name => $value) {
            $display         = $this->masker->mask($name, $value);
            $allParameters[] = [$name, \is_scalar($display) || null === $display ? $display : (string) \json_encode($display, \JSON_UNESCAPED_SLASHES)];
            if (\str_starts_with($name, 'kernel.')) {
                $kernelParameters[$name] = $display;
            }
        }

        $blocks[] = Block::items([
            Block::item('kernel.* parameters', [Block::kv($kernelParameters)], (string) \count($kernelParameters)),
            Block::item('All container parameters', [Block::table(['Name', 'Value'], $allParameters)], (string) \count($allParameters)),
        ]);

        return [
            'summary' => \sprintf('%s · %s · %d bundles', Kernel::VERSION, $this->kernel->getEnvironment(), \count($this->kernel->getBundles())),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bundles(): array
    {
        $file = $this->kernel->getProjectDir() . '/config/bundles.php';
        /** @var array<class-string, array<string, bool>> $envs */
        $envs = \is_readable($file) ? require $file : [];

        $rows = [];
        foreach ($this->kernel->getBundles() as $name => $bundle) {
            $enabledIn = \array_keys(\array_filter($envs[$bundle::class] ?? []));
            $rows[]    = [$name, $bundle::class, $enabledIn];
        }

        return Block::table(['Bundle', 'Class', 'Enabled in'], $rows, 'Bundles');
    }

    private function parameter(string $name): mixed
    {
        return $this->parameters->has($name) ? $this->parameters->get($name) : null;
    }
}
