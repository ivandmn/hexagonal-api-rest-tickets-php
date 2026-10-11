<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

$kernel = require __DIR__ . '/Kernel.php';
assert($kernel instanceof KernelInterface);

return new Application($kernel);
