<?php

declare(strict_types=1);

namespace App\UI\Http\Info;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One card of the /info debug page. Implementations are picked up automatically
 * and ordered by their #[AsTaggedItem] priority.
 */
#[AutoconfigureTag(self::TAG)]
interface InfoSectionInterface
{
    public const TAG = 'app.info_section';

    /**
     * Stable identifier used in `?sections=` and in the page anchors.
     */
    public function id(): string;

    public function title(): string;

    public function icon(): string;

    /**
     * @return array{summary: string|null, blocks: list<array<string, mixed>>}
     */
    public function collect(): array;
}
