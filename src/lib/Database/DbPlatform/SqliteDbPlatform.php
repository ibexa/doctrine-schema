<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database\DbPlatform;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;

/**
 * @internal
 */
final class SqliteDbPlatform extends SqlitePlatform implements DbPlatformInterface
{
    public function addEventSubscribers(EventManager $eventManager): void
    {
        // Nothing to do
    }

    public function getCreateTableSQL(Table $table, $createFlags = null): array
    {
        $createFlags = $createFlags ?? self::CREATE_INDEXES | self::CREATE_FOREIGNKEYS;

        $primaryKeyIndex = $table->getPrimaryKey();
        $hasCompositePK = $primaryKeyIndex !== null && count($primaryKeyIndex->getColumns()) > 1;

        // drop autoincrement if table as composite key as this is not supported
        if ($hasCompositePK) {
            foreach ($table->getColumns() as $column) {
                $column->setAutoincrement(false);
            }
        }

        return parent::getCreateTableSQL($table, $createFlags);
    }

    public function getDriverName(): string
    {
        return 'pdo_sqlite';
    }

    /**
     * Override default behavior of Sqlite db platform to force generating foreign keys.
     */
    public function supportsForeignKeyConstraints(): bool
    {
        return true;
    }

    /**
     * Override default behavior of Sqlite db platform not to throw exception for unsupported operation of dropping FKs.
     *
     * {@inheritdoc}
     */
    public function getDropForeignKeySQL($foreignKey, $table): string
    {
        // dropping FKs is not supported by Sqlite

        return '-- ';
    }

    /**
     * Override default behavior of Sqlite db platform not to throw exception for unsupported operation of creating FKs.
     *
     * {@inheritdoc}
     */
    public function getCreateForeignKeySQL(ForeignKeyConstraint $foreignKey, $table): string
    {
        return '-- ';
    }

    public function configure(Configuration $dbalConfiguration): void
    {
        $dbalConfiguration->setMiddlewares([new EnableForeignKeys()]);
    }
}
