<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsTaggedItem(priority: 30)]
final readonly class EnvironmentSection implements InfoSectionInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'environment';
    }

    public function title(): string
    {
        return 'Environment variables';
    }

    public function icon(): string
    {
        return '🌱';
    }

    public function collect(): array
    {
        $dotenvVars = $_SERVER['SYMFONY_DOTENV_VARS'] ?? '';
        $fromDotenv = \array_filter(\explode(',', \is_string($dotenvVars) ? $dotenvVars : ''));
        $declared   = $this->declaredInDotenvFiles();
        $process    = \getenv();

        $names = \array_unique([...$fromDotenv, ...\array_keys($declared), ...\array_keys($process)]);
        \sort($names);

        $rows       = [];
        $overridden = 0;
        foreach ($names as $name) {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? $process[$name] ?? null;

            if (\in_array($name, $fromDotenv, true)) {
                $source = Block::badge('.env: ' . \implode(', ', $declared[$name] ?? ['?']), 'info');
            } elseif (isset($declared[$name])) {
                // Real env vars win over .env files: a classic "why is my .env.local ignored?".
                $source = Block::badge('real env (overrides ' . \implode(', ', $declared[$name]) . ')', 'warn');
                ++$overridden;
            } else {
                $source = Block::badge('real env', 'muted');
            }

            $rows[] = [$name, $this->masker->mask($name, \is_scalar($value) ? (string) $value : $value), $source];
        }

        $blocks = [
            Block::note('Secrets are masked. Values loaded by Symfony from .env files are blue; real environment variables (container/process) always win over .env files — amber marks the ones that shadow a value declared in a .env file.', 'info'),
            Block::table(['Variable', 'Value', 'Source'], $rows),
        ];

        return [
            'summary' => \sprintf('%d vars · %d from .env · %d overridden', \count($rows), \count($fromDotenv), $overridden),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @return array<string, list<string>> variable name => .env files declaring it
     */
    private function declaredInDotenvFiles(): array
    {
        $env      = $this->kernel->getEnvironment();
        $declared = [];

        foreach (['.env', '.env.local', '.env.' . $env, '.env.' . $env . '.local'] as $file) {
            $path = $this->kernel->getProjectDir() . '/' . $file;
            if (!\is_readable($path)) {
                continue;
            }

            try {
                $variables = new Dotenv()->parse((string) \file_get_contents($path), $path);
            } catch (\Throwable) {
                continue;
            }

            foreach (\array_keys($variables) as $name) {
                if (\is_string($name)) {
                    $declared[$name][] = $file;
                }
            }
        }

        return $declared;
    }
}
