<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Builder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\DoctrineSchema\Builder\SchemaApplier;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\DoctrineSchema\Builder\SchemaApplier
 */
final class SchemaApplierTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
    }

    public function testApplySchemaCreatesTables(): void
    {
        (new SchemaApplier($this->connection))->applySchema($this->buildSchema());

        self::assertTrue($this->connection->getSchemaManager()->tablesExist(['applier_test']));
    }

    public function testApplySchemaTwiceFailsWithoutDropping(): void
    {
        $applier = new SchemaApplier($this->connection);
        $applier->applySchema($this->buildSchema());

        $this->expectExceptionMessageMatches('/applier_test/');

        $applier->applySchema($this->buildSchema());
    }

    public function testApplySchemaIsRepeatableWhenDroppingExistingTables(): void
    {
        $applier = new SchemaApplier($this->connection);
        $applier->applySchema($this->buildSchema());
        $this->connection->insert('applier_test', ['id' => 1]);

        $applier->applySchema($this->buildSchema(), true);

        // the table was dropped and recreated, so the row is gone
        self::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM applier_test')
        );
    }

    public function testDroppingIsSkippedForTablesThatDoNotExistYet(): void
    {
        (new SchemaApplier($this->connection))->applySchema($this->buildSchema(), true);

        self::assertTrue($this->connection->getSchemaManager()->tablesExist(['applier_test']));
    }

    private function buildSchema(): Schema
    {
        $schema = new Schema();
        $table = $schema->createTable('applier_test');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);

        return $schema;
    }
}
