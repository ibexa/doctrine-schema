<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database\DbPlatform;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * @internal
 */
final class PostgreSqlDbPlatform extends PostgreSQLPlatform implements DbPlatformInterface
{
    public function getDriverName(): string
    {
        return 'pdo_pgsql';
    }

    public function getCreateSchemaSQL(string $schemaName): string
    {
        return 'CREATE SCHEMA IF NOT EXISTS ' . $schemaName;
    }

    /**
     * Returns the SQL snippet to drop an existing table.
     */
    public function getDropTableSQL(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $table . ' CASCADE';
    }

    public function configure(Configuration $dbalConfiguration): void
    {
        // Nothing to do
    }
}
