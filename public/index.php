<?php

use App\Infrastructure\Symfony\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    \assert(\is_string($context['APP_ENV']));

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
