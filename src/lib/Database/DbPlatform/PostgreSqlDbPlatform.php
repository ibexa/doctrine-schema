<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database\DbPlatform;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;

/**
 * @internal
 */
final class PostgreSqlDbPlatform extends PostgreSQLPlatform implements DbPlatformInterface
{
    public function addEventSubscribers(EventManager $eventManager): void
    {
        // Nothing to do
    }

    public function getDriverName(): string
    {
        return 'pdo_pgsql';
    }

    public function getCreateSchemaSQL($schemaName): string
    {
        return 'CREATE SCHEMA IF NOT EXISTS ' . $schemaName;
    }

    /**
     * Returns the SQL snippet to drop an existing table.
     *
     * @param \Doctrine\DBAL\Schema\Table|string $table
     *
     * @throws \InvalidArgumentException
     */
    public function getDropTableSQL($table): string
    {
        if ($table instanceof Table) {
            $table = $table->getQuotedName($this);
        }

        if (!is_string($table)) {
            throw new InvalidArgumentException('getDropTableSQL() expects $table parameter to be string or \Doctrine\DBAL\Schema\Table.');
        }

        return 'DROP TABLE IF EXISTS ' . $table . ' CASCADE';
    }

    public function configure(Configuration $dbalConfiguration): void
    {
        // Nothing to do
    }
}
