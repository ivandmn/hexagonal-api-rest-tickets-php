<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use Composer\InstalledVersions;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 35)]
final readonly class ComposerSection implements InfoSectionInterface
{
    public function id(): string
    {
        return 'composer';
    }

    public function title(): string
    {
        return 'Composer packages';
    }

    public function icon(): string
    {
        return '📦';
    }

    public function collect(): array
    {
        $root     = InstalledVersions::getRootPackage();
        $packages = [];
        $dev      = 0;

        foreach (InstalledVersions::getAllRawData() as $dataset) {
            foreach ($dataset['versions'] as $name => $package) {
                // Provided/replaced virtual packages have no version of their own.
                if ($name === $root['name'] || !isset($package['pretty_version']) || isset($packages[$name])) {
                    continue;
                }

                $isDev = $package['dev_requirement'];
                $dev += (int) $isDev;
                $packages[$name] = [
                    Block::link('https://packagist.org/packages/' . $name, $name),
                    $package['pretty_version'],
                    $package['type'] ?? null,
                    $isDev ? Block::badge('dev', 'muted') : Block::badge('prod', 'info'),
                    isset($package['reference']) ? \substr((string) $package['reference'], 0, 7) : null,
                ];
            }
        }
        \ksort($packages);

        return [
            'summary' => \sprintf('%d packages (%d dev)', \count($packages), $dev),
            'blocks'  => [
                Block::kv([
                    'Root package'   => $root['name'],
                    'Version'        => $root['pretty_version'],
                    'Reference'      => $root['reference'] ? \substr((string) $root['reference'], 0, 12) : null,
                    'Dev install'    => Block::flag((bool) $root['dev'], 'yes', 'no'),
                    'Install path'   => \realpath($root['install_path']) ?: $root['install_path'],
                ], 'Project'),
                Block::table(['Package', 'Version', 'Type', 'Scope', 'Ref'], \array_values($packages), 'Installed packages'),
            ],
        ];
    }
}
