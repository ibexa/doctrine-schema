<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Database;

/**
 * Database platform identifiers recognized by {@see DatabasePlatformResolver}.
 *
 * Provided as class constants rather than an enum for PHP 7.4 compatibility.
 */
final class DatabasePlatformName
{
    public const MYSQL = 'mysql';

    public const POSTGRESQL = 'postgresql';

    public const SQLITE = 'sqlite';

    private function __construct()
    {
        // intentionally prevent instantiation
    }

    /**
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::MYSQL,
            self::POSTGRESQL,
            self::SQLITE,
        ];
    }
}
