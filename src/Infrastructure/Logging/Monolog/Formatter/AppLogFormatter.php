<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging\Monolog\Formatter;

use Monolog\Formatter\LineFormatter;

final class AppLogFormatter extends LineFormatter
{
    public const string DATE_FORMAT = 'Y-m-d\TH:i:s.vP';

    public const string FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    public function __construct()
    {
        parent::__construct(
            format: self::FORMAT,
            dateFormat: self::DATE_FORMAT,
            allowInlineLineBreaks: false,
            ignoreEmptyContextAndExtra: true,
            includeStacktraces: false,
        );
    }
}
