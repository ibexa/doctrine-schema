<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Importer;

use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use PHPUnit\Framework\TestCase;

class SchemaImporterTest extends TestCase
{
    /**
     * Create test matrix as a combination of all input files and all platform and their expected SQL outputs.
     *
     * @see testImportFromFile
     *
     * @phpstan-return iterable<array{non-empty-string, \Doctrine\DBAL\Schema\Schema}>
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function providerForTestImportFromFile(): iterable
    {
        $simplePk = new Schema();
        $table = $simplePk->createTable('my_table');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);

        yield ['00-simple_pk.yaml', $simplePk];

        $compositePk = new Schema();
        $table = $compositePk->createTable('my_table');
        $table->addColumn('id', 'integer', ['default' => '0']);
        $table->addColumn('version', 'integer', ['default' => '0']);
        $table->setPrimaryKey(['id', 'version']);
        $table->addColumn('name', 'string', ['length' => 255]);

        yield ['01-composite_pk.yaml', $compositePk];

        $compositePkWithAi = new Schema();
        $table = $compositePkWithAi->createTable('my_table');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('version', 'integer', ['default' => '0']);
        $table->setPrimaryKey(['id', 'version']);
        $table->addColumn('name', 'string', ['length' => 255]);

        yield ['02-composite_pk_with_ai.yaml', $compositePkWithAi];

        $foreignKey = new Schema();
        $mainTable = $foreignKey->createTable('my_main_table');
        $mainTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $mainTable->setPrimaryKey(['id']);
        $mainTable->addColumn('name', 'string', ['length' => 255]);
        $secondaryTable = $foreignKey->createTable('my_secondary_table');
        $secondaryTable->addColumn('id', 'integer', ['autoincrement' => true]);
        $secondaryTable->setPrimaryKey(['id']);
        $secondaryTable->addColumn('main_id', 'integer');
        $secondaryTable->addForeignKeyConstraint(
            'my_main_table',
            ['main_id'],
            ['id'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE'],
            'fk_my_secondary_table_id_main'
        );

        yield ['03-foreign_key.yaml', $foreignKey];

        $nullableField = new Schema();
        $table = $nullableField->createTable('my_table');
        $table->addColumn('data', 'integer')->setNotnull(false);

        yield ['04-nullable_field.yaml', $nullableField];

        $varcharLength = new Schema();
        $table = $varcharLength->createTable('my_table');
        $table->addColumn('name', 'string', ['length' => 64]);

        yield ['05-varchar_length.yaml', $varcharLength];

        $index = new Schema();
        $table = $index->createTable('my_table');
        $table->addColumn('data1', 'integer');
        $table->addColumn('data2', 'integer');
        $table->addColumn('name', 'string', ['length' => 255]);
        $table->addIndex(['data1'], 'ix_simple', [], []);
        $table->addIndex(['data1', 'data2'], 'ix_composite', [], []);
        $table->addUniqueIndex(['name'], 'ux_name', []);

        yield ['06-index.yaml', $index];

        $numericOptions = new Schema();
        $table = $numericOptions->createTable('my_table');
        $table->addColumn('data', 'decimal', ['precision' => 19, 'scale' => 4]);

        yield ['07-numeric-options.yaml', $numericOptions];

        $fieldIndex = new Schema();
        $table = $fieldIndex->createTable('my_table');
        $table->addColumn('data1', 'integer');
        $table->addIndex(['data1'], 'data1_idx');
        $table->addColumn('data2', 'integer');
        $table->addIndex(['data2'], 'data2_idx');
        $table->addColumn('data3', 'string', ['length' => 255]);
        $table->addUniqueIndex(['data3'], 'data3_uidx');

        yield ['simple-field-index.yaml', $fieldIndex];

        $simpleForeignKey = new Schema();
        $table = $simpleForeignKey->createTable('my_table');
        $table->addColumn('id', 'integer');
        $table->addForeignKeyConstraint('foreign_table_id', ['id'], ['foreign_id'], [], 'id_fk');
        $table->setPrimaryKey(['id']);
        $table->addColumn('data1', 'integer');
        $table->addForeignKeyConstraint('foreign_table_1', ['data1'], ['foreign_data1'], []);
        $table->addColumn('data2', 'integer');
        $table->addForeignKeyConstraint(
            'foreign_table_2',
            ['data2'],
            ['foreign_data2'],
            [],
            'foreign_data2_fk_name'
        );
        $table->addIndex(['data2'], 'data2_idx');
        $table->addColumn('data3', 'string', ['length' => 255]);
        $table->addForeignKeyConstraint(
            'foreign_table_3',
            ['data3'],
            ['foreign_data3'],
            [],
            'foreign_data3_fk_name'
        );
        $table->addIndex(['data3'], 'data3_idx');
        $table->addColumn('data4', 'string', ['length' => 255]);
        $table->addForeignKeyConstraint(
            'foreign_table_4',
            ['data4'],
            ['foreign_data4'],
            ['onDelete' => 'CASCADE', 'onUpdate' => 'RESTRICT'],
            'foreign_data4_fk_name'
        );
        $table->addUniqueIndex(['data4'], 'data4_uidx');

        yield ['simple-foreign-key.yaml', $simpleForeignKey];
    }

    public function testStringColumnWithoutLengthGetsTheDefaultLength(): void
    {
        $schema = (new SchemaImporter())->importFromFile(__DIR__ . '/_fixtures/01-composite_pk.yaml');

        self::assertSame(255, $schema->getTable('my_table')->getColumn('name')->getLength());
        self::assertStringContainsString(
            'name VARCHAR(255)',
            implode("\n", $schema->toSql(new MySQL80Platform()))
        );
    }

    /**
     * @dataProvider providerForTestImportFromFile
     *
     * @param string $yamlSchemaDefinitionFile custom Yaml schema definition fixture file name
     *
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     * @throws \Doctrine\DBAL\Exception
     */
    public function testImportFromFile(
        string $yamlSchemaDefinitionFile,
        Schema $expectedSchema
    ): void {
        $yamlSchemaDefinitionFilePath = realpath(__DIR__ . "/_fixtures/{$yamlSchemaDefinitionFile}");
        if (false === $yamlSchemaDefinitionFilePath) {
            self::markTestIncomplete("Missing output fixture {$yamlSchemaDefinitionFilePath}");
        }

        $importer = new SchemaImporter();
        $actualSchema = $importer->importFromFile($yamlSchemaDefinitionFilePath);

        self::assertEquals(
            $expectedSchema,
            $actualSchema,
            "Yaml schema definition {$yamlSchemaDefinitionFile} produced unexpected Schema object"
        );
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function testTableImportFailsIfUnhandledKeys(): void
    {
        $importer = new SchemaImporter();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Unhandled property in schema configuration for "my_table". "foo" keys are not allowed. Allowed keys:'
            . ' "id", "fields", "foreignKeys", "indexes", "uniqueConstraints".'
        );
        $importer->importFromFile(__DIR__ . '/_fixtures/failing-import.yaml');
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function testColumnImportFailsIfUnhandledKeys(): void
    {
        $importer = new SchemaImporter();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'Unhandled property in schema configuration for "my_table.fields.foo". "bar" keys are not allowed. Allowed keys:'
            . ' "length", "scale", "precision", "type", "nullable", "options", "index", "foreignKey".'
        );
        $importer->importFromFile(__DIR__ . '/_fixtures/failing-import-column.yaml');
    }

    /**
     * DBAL 4 refuses to generate a VARCHAR without a length. 6.0 still fills in a default so that
     * schemas written before it keep working, and deprecates relying on that.
     *
     * @group legacy
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function testStringColumnWithoutLengthDefaultsTo255(): void
    {
        $importer = new SchemaImporter();

        $schema = $importer->importFromFile(__DIR__ . '/_fixtures/string-without-length.yaml');

        self::assertSame(255, $schema->getTable('my_table')->getColumn('name')->getLength());
    }
}
