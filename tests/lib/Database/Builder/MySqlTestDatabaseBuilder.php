<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\Builder;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException;

class MySqlTestDatabaseBuilder implements TestDatabaseBuilder
{
    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException
     */
    public function buildDatabase(): Connection
    {
        if (false === ($url = getenv('MYSQL_DATABASE_URL'))) {
            throw new TestDatabaseConfigurationException('To run MySQL-specific test set MYSQL_DATABASE_URL environment variable');
        }

        $connection = DriverManager::getConnection(
            (new DsnParser([
                'mysql' => 'pdo_mysql',
                'mysql2' => 'pdo_mysql',
                'mysqli' => 'mysqli',
            ]))->parse($url),
            new Configuration()
        );
        // cleanup database
        $statements = $connection->createSchemaManager()->introspectSchema()->toDropSql(
            $connection->getDatabasePlatform()
        );
        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        return $connection;
    }
}
