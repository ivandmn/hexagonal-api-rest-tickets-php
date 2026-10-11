<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InfoControllerTest extends WebTestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function infoUrls(): iterable
    {
        yield ['/info'];
        yield ['/info?format=json'];
        yield ['/info/phpinfo'];
        yield ['/info/database'];
    }

    /**
     * The debug page exposes the whole runtime: it must only be routed in local/dev.
     */
    #[DataProvider('infoUrls')]
    public function test_info_pages_are_not_routed_outside_local_and_dev(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertSame('test', static::getContainer()->getParameter('kernel.environment'));
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
