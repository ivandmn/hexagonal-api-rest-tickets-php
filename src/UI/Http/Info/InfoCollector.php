<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class InfoCollector
{
    /** @var array<string, InfoSectionInterface> */
    private array $sections;

    /**
     * @param iterable<InfoSectionInterface> $sections
     */
    public function __construct(
        #[AutowireIterator(InfoSectionInterface::TAG)]
        iterable $sections,
    ) {
        $indexed = [];
        foreach ($sections as $section) {
            $indexed[$section->id()] = $section;
        }

        $this->sections = $indexed;
    }

    /**
     * @return list<array{id: string, title: string, icon: string}>
     */
    public function available(): array
    {
        return \array_values(\array_map(
            static fn (InfoSectionInterface $section): array => ['id' => $section->id(), 'title' => $section->title(), 'icon' => $section->icon()],
            $this->sections,
        ));
    }

    /**
     * Collects the requested sections (all of them when `$only` is empty). A failing section
     * is reported in its own card instead of breaking the whole page.
     *
     * @param list<string> $only
     *
     * @return list<array{id: string, title: string, icon: string, summary: string|null, blocks: list<array<string, mixed>>, error: string|null, ms: float}>
     */
    public function collect(array $only = []): array
    {
        $result = [];

        foreach ($this->sections as $id => $section) {
            if ([] !== $only && !\in_array($id, $only, true)) {
                continue;
            }

            $start = \hrtime(true);

            try {
                $data  = $section->collect();
                $error = null;
            } catch (\Throwable $exception) {
                $data  = ['summary' => 'error', 'blocks' => []];
                $error = \sprintf('%s: %s (%s:%d)', $exception::class, $exception->getMessage(), \basename($exception->getFile()), $exception->getLine());
            }

            $result[] = [
                'id'      => $id,
                'title'   => $section->title(),
                'icon'    => $section->icon(),
                'summary' => $data['summary'],
                'blocks'  => $data['blocks'],
                'error'   => $error,
                'ms'      => \round((\hrtime(true) - $start) / 1e6, 2),
            ];
        }

        return $result;
    }
}
