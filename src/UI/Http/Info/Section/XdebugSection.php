<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 65)]
final readonly class XdebugSection implements InfoSectionInterface
{
    public function id(): string
    {
        return 'xdebug';
    }

    public function title(): string
    {
        return 'Xdebug';
    }

    public function icon(): string
    {
        return '🐞';
    }

    public function collect(): array
    {
        if (!\extension_loaded('xdebug')) {
            return ['summary' => 'not loaded', 'blocks' => [Block::note('Xdebug is not loaded: no debugging, but no overhead either.', 'muted')]];
        }

        $modes = \function_exists('xdebug_info') ? \xdebug_info('mode') : \explode(',', (string) \ini_get('xdebug.mode'));
        $modes = \array_values(\array_filter(\array_map(static fn (mixed $mode): string => \is_string($mode) ? \trim($mode) : '', (array) $modes)));

        $blocks = [
            Block::tags(\array_map(
                // develop/coverage/profile/trace slow every request down; debug + trigger is the cheap one.
                static fn (string $mode): array => ['label' => $mode, 'tone' => \in_array($mode, ['debug', 'off'], true) ? 'ok' : 'warn'],
                [] === $modes ? ['off'] : $modes,
            ), 'Active modes (amber = adds overhead to every request)'),
            Block::kv([
                'Version'               => \phpversion('xdebug') ?: null,
                'Start with request'    => (string) \ini_get('xdebug.start_with_request'),
                'Trigger value'         => (string) \ini_get('xdebug.trigger_value') ?: Block::badge('any value', 'muted'),
                'Client host'           => (string) \ini_get('xdebug.client_host'),
                'Client port'           => (string) \ini_get('xdebug.client_port'),
                'Discover client host'  => Block::flag((bool) \ini_get('xdebug.discover_client_host')),
                'IDE key'               => (string) \ini_get('xdebug.idekey') ?: null,
                'PHP_IDE_CONFIG'        => \getenv('PHP_IDE_CONFIG') ?: Block::badge('not set', 'warn'),
                'Log'                   => (string) \ini_get('xdebug.log') ?: null,
                'Log level'             => (string) \ini_get('xdebug.log_level'),
                'Output dir'            => (string) \ini_get('xdebug.output_dir'),
                'Debugger active now'   => Block::flag(\function_exists('xdebug_is_debugger_active') && \xdebug_is_debugger_active(), 'yes', 'no'),
            ], 'Settings'),
        ];

        if ('trigger' === \ini_get('xdebug.start_with_request')) {
            $blocks[] = Block::note('Debugging starts only when a request carries XDEBUG_TRIGGER (cookie, query string or env var). Use the "Xdebug trigger" switch in the header to set the cookie for this browser, or add ?XDEBUG_TRIGGER=1 to a URL.', 'info');
        }

        return [
            'summary' => \sprintf('%s · %s', \phpversion('xdebug') ?: '?', \implode(', ', $modes) ?: 'off'),
            'blocks'  => $blocks,
        ];
    }
}
