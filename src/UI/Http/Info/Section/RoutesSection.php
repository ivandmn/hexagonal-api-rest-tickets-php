<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

#[AsTaggedItem(priority: 45)]
final readonly class RoutesSection implements InfoSectionInterface
{
    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    public function id(): string
    {
        return 'routes';
    }

    public function title(): string
    {
        return 'Routes';
    }

    public function icon(): string
    {
        return '🛣️';
    }

    public function collect(): array
    {
        $current     = $this->requestStack->getMainRequest()?->attributes->get('_route');
        $application = [];
        $framework   = [];

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            $row        = [
                $name === $current ? Block::badge($name, 'info') : $name,
                [] === $route->getMethods() ? Block::badge('ANY', 'muted') : \implode('|', $route->getMethods()),
                $route->getPath(),
                \is_string($controller) ? \str_replace('App\UI\Http\Controller\\', '', $controller) : \get_debug_type($controller),
            ];

            // Profiler, error previews and other framework routes start with an underscore.
            if (\str_starts_with($name, '_')) {
                $framework[] = $row;
            } else {
                $application[] = $row;
            }
        }

        $columns = ['Name', 'Methods', 'Path', 'Controller'];

        return [
            'summary' => \sprintf('%d application · %d framework', \count($application), \count($framework)),
            'blocks'  => [
                Block::table($columns, $application, 'Application routes'),
                Block::items([Block::item('Framework routes (profiler, error previews…)', [Block::table($columns, $framework)], (string) \count($framework))]),
            ],
        ];
    }
}
