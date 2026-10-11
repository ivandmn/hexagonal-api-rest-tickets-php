<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\Format;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

#[AsTaggedItem(priority: 90)]
final readonly class PhpSection implements InfoSectionInterface
{
    /** Directives worth seeing at a glance; the full list is below them. */
    private const KEY_DIRECTIVES = [
        'memory_limit', 'max_execution_time', 'max_input_time', 'max_input_vars',
        'post_max_size', 'upload_max_filesize', 'max_file_uploads',
        'display_errors', 'display_startup_errors', 'log_errors', 'error_log', 'error_reporting', 'html_errors',
        'date.timezone', 'default_charset', 'expose_php', 'allow_url_fopen',
        'realpath_cache_size', 'realpath_cache_ttl', 'output_buffering', 'zend.assertions',
        'session.save_handler', 'session.save_path', 'disable_functions', 'open_basedir', 'variables_order',
    ];

    public function __construct(
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'php';
    }

    public function title(): string
    {
        return 'PHP';
    }

    public function icon(): string
    {
        return '🐘';
    }

    public function collect(): array
    {
        $scanned       = \php_ini_scanned_files();
        $memoryLimit   = Format::iniBytes(\ini_get('memory_limit'));
        $realpathLimit = Format::iniBytes(\ini_get('realpath_cache_size'));

        $runtime = [
            'Version'          => \PHP_VERSION,
            'SAPI'             => \PHP_SAPI,
            'Zend Engine'      => \zend_version(),
            'Thread safety'    => Block::flag(\ZEND_THREAD_SAFE, 'ZTS', 'NTS'),
            'Debug build'      => Block::flag(\ZEND_DEBUG_BUILD, 'yes', 'no'),
            'Architecture'     => (\PHP_INT_SIZE * 8) . '-bit',
            'Binary'           => \PHP_BINARY,
            'Loaded php.ini'   => \php_ini_loaded_file() ?: Block::badge('none', 'warn'),
            'Scan dir'         => \getenv('PHP_INI_SCAN_DIR') ?: \PHP_CONFIG_FILE_SCAN_DIR,
            'Scanned .ini'     => false === $scanned ? [] : \array_map('trim', \explode(',', \trim($scanned))),
            'Extension dir'    => \ini_get('extension_dir') ?: null,
            'Include path'     => \get_include_path(),
            'GC'               => Block::flag(\gc_enabled()),
            'GC runs'          => \gc_status()['runs'],
            'GC collected'     => \gc_status()['collected'],
        ];

        $blocks = [
            Block::kv($runtime, 'Runtime'),
            Block::meter('Memory (this request vs memory_limit)', \memory_get_usage(true), $memoryLimit > 0 ? $memoryLimit : \memory_get_usage(true)),
        ];

        if ($realpathLimit > 0) {
            $blocks[] = Block::meter('Realpath cache', \realpath_cache_size(), $realpathLimit);
        }

        $all  = \ini_get_all(null, true) ?: [];
        $rows = [];
        foreach (self::KEY_DIRECTIVES as $directive) {
            if (\is_array($all[$directive] ?? null)) {
                $rows[] = $this->directiveRow($directive, $all[$directive]);
            }
        }
        $blocks[] = Block::table(['Directive', 'Local value', 'Master value'], $rows, 'Key directives');

        \ksort($all);
        $allRows = [];
        foreach ($all as $directive => $values) {
            if (\is_array($values)) {
                $allRows[] = $this->directiveRow((string) $directive, $values);
            }
        }
        $blocks[] = Block::items([
            Block::item('All ini directives', [Block::table(['Directive', 'Local value', 'Master value'], $allRows)], (string) \count($allRows)),
        ]);

        return [
            'summary' => \sprintf('%s · %s · %s', \PHP_VERSION, \PHP_SAPI, \ZEND_THREAD_SAFE ? 'ZTS' : 'NTS'),
            'blocks'  => $blocks,
        ];
    }

    /**
     * @param array<mixed> $values ini_get_all() details (local_value, global_value, access)
     *
     * @return list<mixed>
     */
    private function directiveRow(string $directive, array $values): array
    {
        $local  = $this->display($directive, self::string($values, 'local_value'));
        $global = $this->display($directive, self::string($values, 'global_value'));

        // Highlight what this request/runtime overrides compared to php.ini.
        return [$directive, $local !== $global ? Block::badge((string) ($local ?? '(null)'), 'warn') : $local, $global];
    }

    private function display(string $directive, ?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if ('error_reporting' === $directive && \is_numeric($value)) {
            return \sprintf('%s (%s)', Format::errorLevel((int) $value), $value);
        }

        $masked = $this->masker->mask($directive, $value);

        return \is_string($masked) ? $masked : $value;
    }

    /**
     * @param array<mixed> $values
     */
    private static function string(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
