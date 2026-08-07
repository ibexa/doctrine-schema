<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database\DbPlatform;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * @internal
 */
final class SqliteDbPlatform extends SQLitePlatform implements DbPlatformInterface
{
    /**
     * @return list<string>
     */
    public function getCreateTableSQL(Table $table): array
    {
        $primaryKeyIndex = $table->getPrimaryKey();
        $hasCompositePK = $primaryKeyIndex !== null && count($primaryKeyIndex->getColumns()) > 1;

        // drop autoincrement if table as composite key as this is not supported
        if ($hasCompositePK) {
            foreach ($table->getColumns() as $column) {
                $column->setAutoincrement(false);
            }
        }

        return parent::getCreateTableSQL($table);
    }

    public function getDriverName(): string
    {
        return 'pdo_sqlite';
    }

    /**
     * Override default behavior of Sqlite db platform not to throw exception for unsupported operation of dropping FKs.
     *
     * {@inheritdoc}
     */
    public function getDropForeignKeySQL(string $foreignKey, string $table): string
    {
        // dropping FKs is not supported by Sqlite

        return '-- ';
    }

    /**
     * Override default behavior of Sqlite db platform not to throw exception for unsupported operation of creating FKs.
     *
     * {@inheritdoc}
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, string $table): string
    {
        return '-- ';
    }

    public function configure(Configuration $dbalConfiguration): void
    {
        $dbalConfiguration->setMiddlewares([new EnableForeignKeys()]);
    }
}
