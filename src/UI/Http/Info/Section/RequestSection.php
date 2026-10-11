<?php

declare(strict_types=1);

namespace App\UI\Http\Info\Section;

use App\UI\Http\Info\Block;
use App\UI\Http\Info\InfoSectionInterface;
use App\UI\Http\Info\SecretMasker;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsTaggedItem(priority: 25)]
final readonly class RequestSection implements InfoSectionInterface
{
    private const SERVER_KEYS = [
        'SERVER_SOFTWARE', 'SERVER_PROTOCOL', 'SERVER_NAME', 'SERVER_ADDR', 'SERVER_PORT',
        'REMOTE_ADDR', 'REMOTE_PORT', 'DOCUMENT_ROOT', 'SCRIPT_FILENAME', 'REQUEST_TIME_FLOAT', 'HTTPS',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private SecretMasker $masker,
    ) {
    }

    public function id(): string
    {
        return 'request';
    }

    public function title(): string
    {
        return 'This request';
    }

    public function icon(): string
    {
        return '📨';
    }

    public function collect(): array
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return ['summary' => null, 'blocks' => [Block::note('No HTTP request (CLI?).', 'muted')]];
        }

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[] = [$name, $this->masker->mask($name, \implode(', ', $values))];
        }

        $server = [];
        foreach (self::SERVER_KEYS as $key) {
            if ($request->server->has($key)) {
                $server[$key] = $request->server->get($key);
            }
        }

        return [
            'summary' => \sprintf('%s %s from %s', $request->getMethod(), $request->getPathInfo(), $request->getClientIp()),
            'blocks'  => [
                Block::kv([
                    'Method'             => $request->getMethod(),
                    'URL'                => $request->getUri(),
                    'Protocol'           => $request->getProtocolVersion(),
                    'Secure'             => Block::flag($request->isSecure(), 'https', 'http'),
                    'Host'               => $request->getHost(),
                    'Port'               => $request->getPort(),
                    'Client IP'          => $request->getClientIp(),
                    'Client IP chain'    => $request->getClientIps(),
                    'Trusted proxies'    => Request::getTrustedProxies(),
                    'From trusted proxy' => Block::flag($request->isFromTrustedProxy(), 'yes', 'no'),
                    'Locale'             => $request->getLocale(),
                    'Route'              => $request->attributes->get('_route'),
                    'Controller'         => \is_string($controller = $request->attributes->get('_controller')) ? $controller : null,
                ], 'Request'),
                Block::table(['Header', 'Value'], $headers, 'Headers (secrets masked)'),
                Block::kv($server, 'Server parameters'),
            ],
        ];
    }
}
