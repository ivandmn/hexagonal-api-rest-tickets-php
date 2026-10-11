<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsTaggedItem(priority: 80)]
final readonly class ExtensionsSection implements InfoSectionInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'extensions';
    }

    public function title(): string
    {
        return 'PHP extensions';
    }

    public function icon(): string
    {
        return '🧩';
    }

    public function collect(): array
    {
        $zendExtensions = \get_loaded_extensions(true);
        $extensions     = \array_merge(\get_loaded_extensions(), $zendExtensions);
        \natcasesort($extensions);

        $loaded   = \array_map('strtolower', $extensions);
        $required = $this->requiredByComposer();

        $blocks = [];
        if ([] !== $required) {
            $blocks[] = Block::tags(\array_map(
                fn (string $ext): array => ['label' => $ext, 'tone' => $this->isLoaded($ext, $loaded) ? 'ok' : 'danger'],
                $required,
            ), 'Required by composer.json (red = missing)');
        }

        $items = [];
        foreach ($extensions as $extension) {
            $isZend    = \in_array($extension, $zendExtensions, true);
            // Extensions without directives (Core, ctype...) make ini_get_all() warn and return false.
            $ini       = @\ini_get_all($this->iniName($extension), false) ?: [];
            $functions = \get_extension_funcs($extension) ?: [];

            $itemBlocks = [];
            if ([] !== $ini) {
                \ksort($ini);
                $itemBlocks[] = Block::kv($this->masker->maskArray($ini), 'Directives');
            }
            if ([] !== $functions) {
                \sort($functions);
                $itemBlocks[] = Block::tags($functions, \sprintf('Functions (%d)', \count($functions)));
            }
            if ([] === $itemBlocks) {
                $itemBlocks[] = Block::note('No ini directives or functions exposed.', 'muted');
            }

            $items[] = Block::item(
                $extension,
                $itemBlocks,
                \phpversion($this->iniName($extension)) ?: null,
                \implode(' · ', \array_filter([
                    $isZend ? 'zend extension' : null,
                    [] !== $ini ? \count($ini) . (1 === \count($ini) ? ' directive' : ' directives') : null,
                    [] !== $functions ? \count($functions) . (1 === \count($functions) ? ' function' : ' functions') : null,
                ])),
                \in_array(\strtolower($extension), $required, true) ? 'info' : 'neutral',
            );
        }

        $blocks[] = Block::items($items, 'Loaded extensions');

        return [
            'summary' => \sprintf('%d loaded (%d zend)', \count($extensions), \count($zendExtensions)),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @return list<string> lowercase extension names from `ext-*` requirements
     */
    private function requiredByComposer(): array
    {
        $file     = $this->kernel->getProjectDir() . '/composer.json';
        $composer = \is_readable($file) ? \json_decode((string) \file_get_contents($file), true) : null;

        if (!\is_array($composer)) {
            return [];
        }

        $packages   = \array_keys(\array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []));
        $extensions = \array_values(\array_map(
            static fn (string $package): string => \strtolower(\substr($package, 4)),
            \array_filter($packages, static fn (string $package): bool => \str_starts_with($package, 'ext-')),
        ));
        \sort($extensions);

        return $extensions;
    }

    /**
     * @param array<string> $loaded
     */
    private function isLoaded(string $extension, array $loaded): bool
    {
        return \in_array($extension, $loaded, true) || ('opcache' === $extension && \in_array('zend opcache', $loaded, true));
    }

    /**
     * ini_get_all()/phpversion() know "Zend OPcache" as "opcache".
     */
    private function iniName(string $extension): string
    {
        return 'Zend OPcache' === $extension ? 'opcache' : $extension;
    }
}
