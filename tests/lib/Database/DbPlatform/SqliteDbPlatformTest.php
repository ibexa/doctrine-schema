<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\DbPlatform;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform;
use Ibexa\Tests\DoctrineSchema\Database\TestDatabaseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform
 * @covers \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseFactory
 */
final class SqliteDbPlatformTest extends TestCase
{
    private TestDatabaseFactory $testDatabaseFactory;

    private SqliteDbPlatform $sqliteDbPlatform;

    public function setUp(): void
    {
        $this->sqliteDbPlatform = new SqliteDbPlatform();
        $this->testDatabaseFactory = new TestDatabaseFactory();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException
     */
    public function testDatabaseFactoryEnablesForeignKeys(): void
    {
        $connection = $this->testDatabaseFactory->prepareAndConnect($this->sqliteDbPlatform);
        $schema = $connection->createSchemaManager()->introspectSchema();

        $primaryTable = $schema->createTable('my_primary_table');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(['id']);

        $secondaryTable = $schema->createTable('my_secondary_table');
        $secondaryTable->addColumn('id', 'integer');
        $secondaryTable->setPrimaryKey(['id']);
        $secondaryTable->addForeignKeyConstraint($primaryTable, ['id'], ['id']);

        // persist table structure
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $query) {
            $connection->executeStatement($query);
        }

        $connection->insert($primaryTable->getName(), ['id' => 1], [ParameterType::INTEGER]);
        $connection->insert($secondaryTable->getName(), ['id' => 1], [ParameterType::INTEGER]);

        // insert a broken record
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');
        $connection->insert($secondaryTable->getName(), ['id' => 2], [ParameterType::INTEGER]);
    }

    /**
     * For external usage (e.g.: by ibexa/core, a configure method needs to be called to enable foreign keys).
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function testConfigureEnablesForeignKeys(): void
    {
        $configuration = new Configuration();
        $this->sqliteDbPlatform->configure($configuration);

        $connection = DriverManager::getConnection(
            [
                'url' => 'sqlite:///:memory:',
                'platform' => $this->sqliteDbPlatform,
            ],
            $configuration
        );
        self::assertTrue(
            (bool)$connection->executeQuery('PRAGMA foreign_keys')->fetchOne(),
            'Foreign keys are not enabled'
        );
    }
}
