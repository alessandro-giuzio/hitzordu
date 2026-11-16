<?php

declare(strict_types=1);

namespace Hitzordu\Core;

final class Core
{
    public const VERSION = '0.1.0';

    public static function version(): string
    {
        return self::VERSION;
    }
}
