<?php

declare(strict_types=1);

use App\Infrastructure\Symfony\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!isset($GLOBALS['phpstanKernel'])) {
    // Pinned to "test", whatever APP_ENV the shell has: phpstan.dist.neon reads that environment's
    // container (`make phpstan` warms it up first), and every developer and CI analyse the same one.
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
    new Dotenv()->bootEnv(dirname(__DIR__, 3) . '/.env');
    $kernel = new Kernel('test', true);
    $kernel->boot();

    $GLOBALS['phpstanKernel'] = $kernel;
}

return $GLOBALS['phpstanKernel'];
