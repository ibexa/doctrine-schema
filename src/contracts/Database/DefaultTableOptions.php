<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Database;

/**
 * Table options applied to every table Ibexa generates from its Yaml schema definitions.
 *
 * Only MySQL and MariaDB read these; PostgreSQL and SQLite ignore unknown table options.
 */
final class DefaultTableOptions
{
    public const string CHARSET = 'utf8mb4';

    public const string COLLATION = 'utf8mb4_unicode_520_ci';

    public const string ENGINE = 'InnoDB';

    /**
     * @var array{charset: string, collation: string, engine: string}
     */
    public const array AS_ARRAY = [
        'charset' => self::CHARSET,
        'collation' => self::COLLATION,
        'engine' => self::ENGINE,
    ];

    private function __construct()
    {
    }
}
