<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsTaggedItem(priority: 10)]
final readonly class PhpInfoSection implements InfoSectionInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function id(): string
    {
        return 'phpinfo';
    }

    public function title(): string
    {
        return 'phpinfo()';
    }

    public function icon(): string
    {
        return '📜';
    }

    public function collect(): array
    {
        $url = $this->urlGenerator->generate('info_phpinfo');

        return [
            'summary' => 'raw output',
            'blocks'  => [
                Block::note(\sprintf('The classic phpinfo() page, loaded when opened. The "Environment" and "PHP Variables" sections are left out because they expose secrets (see "Environment variables" above, masked). Open it standalone: %s', $url), 'muted'),
                Block::frame($url, 'phpinfo()'),
            ],
        ];
    }
}
