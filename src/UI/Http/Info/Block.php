<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

/**
 * Factories for the building blocks a section is made of. Every block is a plain
 * array so the same data renders as HTML (templates/info) or as JSON.
 */
final class Block
{
    /**
     * Key/value rows. Values may be scalars, null, lists, or a badge()/link().
     *
     * @param array<mixed> $rows
     *
     * @return array<string, mixed>
     */
    public static function kv(array $rows, ?string $title = null): array
    {
        return ['type' => 'kv', 'title' => $title, 'rows' => $rows];
    }

    /**
     * @param list<string>      $columns
     * @param list<list<mixed>> $rows
     *
     * @return array<string, mixed>
     */
    public static function table(array $columns, array $rows, ?string $title = null): array
    {
        return ['type' => 'table', 'title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    /**
     * Collapsible sub-entries (one per extension, entity, bundle...).
     *
     * @param list<array<string, mixed>> $items built with item()
     *
     * @return array<string, mixed>
     */
    public static function items(array $items, ?string $title = null): array
    {
        return ['type' => 'items', 'title' => $title, 'items' => $items];
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return array<string, mixed>
     */
    public static function item(string $title, array $blocks, ?string $badge = null, ?string $meta = null, string $tone = 'neutral'): array
    {
        return ['title' => $title, 'badge' => $badge, 'meta' => $meta, 'tone' => $tone, 'blocks' => $blocks];
    }

    /**
     * @return array<string, mixed>
     */
    public static function code(string $content, ?string $title = null, string $language = 'json'): array
    {
        return ['type' => 'code', 'title' => $title, 'language' => $language, 'content' => $content];
    }

    /**
     * @return array<string, mixed>
     */
    public static function meter(string $label, float|int $used, float|int $total, bool $bytes = true): array
    {
        $percent = $total > 0 ? \min(100, \round($used / $total * 100, 1)) : 0;

        return [
            'type'    => 'meter',
            'label'   => $label,
            'used'    => $bytes ? Format::bytes($used) : (string) $used,
            'total'   => $bytes ? Format::bytes($total) : (string) $total,
            'percent' => $percent,
            'tone'    => $percent >= 90 ? 'danger' : ($percent >= 75 ? 'warn' : 'ok'),
        ];
    }

    /**
     * @param list<array{label: string, tone: string}|string> $tags
     *
     * @return array<string, mixed>
     */
    public static function tags(array $tags, ?string $title = null): array
    {
        return [
            'type'  => 'tags',
            'title' => $title,
            'tags'  => \array_map(static fn (array|string $tag): array => \is_string($tag) ? ['label' => $tag, 'tone' => 'neutral'] : $tag, $tags),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function note(string $text, string $tone = 'info'): array
    {
        return ['type' => 'note', 'tone' => $tone, 'text' => $text];
    }

    /**
     * Fetched from `$url` (JSON) only when the block is opened, so slow probes never block the page.
     *
     * @return array<string, mixed>
     */
    public static function lazy(string $url, string $label): array
    {
        return ['type' => 'lazy', 'url' => $url, 'label' => $label];
    }

    /**
     * @return array<string, mixed>
     */
    public static function frame(string $url, string $label): array
    {
        return ['type' => 'frame', 'url' => $url, 'label' => $label];
    }

    /**
     * @return array{__badge: string, tone: string}
     */
    public static function badge(string $label, string $tone = 'neutral'): array
    {
        return ['__badge' => $label, 'tone' => $tone];
    }

    /**
     * @return array{__badge: string, tone: string}
     */
    public static function flag(bool $value, string $on = 'on', string $off = 'off'): array
    {
        return self::badge($value ? $on : $off, $value ? 'ok' : 'muted');
    }

    /**
     * @return array{__link: string, label: string}
     */
    public static function link(string $url, ?string $label = null): array
    {
        return ['__link' => $url, 'label' => $label ?? $url];
    }
}
