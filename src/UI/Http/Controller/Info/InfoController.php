<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Info;

use App\UI\Http\Info\DatabaseProbe;
use App\UI\Http\Info\InfoCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Personal debug page describing how the application is assembled (PHP, extensions, FrankenPHP,
 * Symfony, Doctrine...). The routes only exist in the local/dev environments.
 */
final class InfoController extends AbstractController
{
    private const ENVS = ['local', 'dev'];

    // Environment & PHP Variables are left out on purpose: they dump every secret unmasked.
    private const PHPINFO_SECTIONS = \INFO_GENERAL | \INFO_CONFIGURATION | \INFO_MODULES;

    public function __construct(
        private readonly InfoCollector $collector,
        private readonly KernelInterface $kernel,
    ) {
    }

    #[Route('/info', name: 'info', methods: ['GET'], env: self::ENVS)]
    public function index(Request $request): Response
    {
        $start    = \hrtime(true);
        $only     = \array_values(\array_filter(\array_map('trim', \explode(',', $request->query->getString('sections')))));
        $sections = $this->collector->collect($only);

        if ('json' === $request->query->getString('format')) {
            return $this->noStore(new JsonResponse([
                'generated_at' => new \DateTimeImmutable()->format(\DATE_ATOM),
                'environment'  => $this->kernel->getEnvironment(),
                'sections'     => $sections,
            ], Response::HTTP_OK, [], false)->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR));
        }

        return $this->noStore($this->render('info/index.html.twig', [
            'available'   => $this->collector->available(),
            'sections'    => $sections,
            'filtered'    => [] !== $only,
            'environment' => $this->kernel->getEnvironment(),
            'debug'       => $this->kernel->isDebug(),
            'xdebug'      => \extension_loaded('xdebug'),
            'collect_ms'  => \round((\hrtime(true) - $start) / 1e6, 1),
        ]));
    }

    #[Route('/info/phpinfo', name: 'info_phpinfo', methods: ['GET'], env: self::ENVS)]
    public function phpinfo(): Response
    {
        \ob_start();
        \phpinfo(self::PHPINFO_SECTIONS);
        $html = (string) \ob_get_clean();

        // Restyle the stock page so it matches /info (and follows the dark mode).
        $html = \str_replace('</head>', $this->renderView('info/_phpinfo_style.html.twig') . '</head>', $html);

        return $this->noStore(new Response($html));
    }

    #[Route('/info/database', name: 'info_database', methods: ['GET'], env: self::ENVS)]
    public function database(DatabaseProbe $probe): JsonResponse
    {
        return $this->noStore(new JsonResponse($probe->probe()));
    }

    /**
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
