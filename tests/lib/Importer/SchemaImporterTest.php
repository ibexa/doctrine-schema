<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Importer;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Deprecations\Deprecation;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SchemaImporterTest extends TestCase
{
    /**
     * Create test matrix as a combination of all input files and all platform and their expected SQL outputs.
     *
     * @see testImportFromFile
     *
     * @phpstan-return iterable<array{non-empty-string, Schema}>
     *
     * @throws Exception
     */
    public static function providerForTestImportFromFile(): iterable
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

        $dbalOptions = new Schema();
        $mainTable = $dbalOptions->createTable('my_main_table');
        $mainTable->addColumn('id', 'integer');
        $mainTable->setPrimaryKey(['id']);
        $table = $dbalOptions->createTable('my_table');
        $table->addColumn('id', 'integer');
        $table->setPrimaryKey(['id']);
        $table->addColumn('name', 'string', [
            'length' => 255,
            'platformOptions' => ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'],
        ]);
        $table->addColumn('status', 'enum', ['values' => ['draft', 'published']]);
        $table->addColumn('main_id', 'integer', ['notnull' => false]);
        $table->addIndex(['status'], 'my_table_published_idx', [], ['where' => "status = 'published'"]);
        $table->addForeignKeyConstraint(
            'my_main_table',
            ['main_id'],
            ['id'],
            ['deferrable' => true, 'deferred' => true, 'match' => 'FULL'],
            'my_table_main_fk'
        );

        yield ['dbal-options.yaml', $dbalOptions];
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
     * @param string $yamlSchemaDefinitionFile custom Yaml schema definition fixture file name
     *
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    #[DataProvider('providerForTestImportFromFile')]
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

        // A schema created from tables gives them its configuration, which Schema::createTable() didn't
        self::assertEquals(
            $expectedSchema->edit()->create(),
            $actualSchema,
            "Yaml schema definition {$yamlSchemaDefinitionFile} produced unexpected Schema object"
        );
    }

    /**
     * DBAL 4.5 deprecated changing a schema or a table, which SchemaBuilderEvent subscribers' tests
     * then report, through the packages' failOnDeprecation.
     *
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    #[DataProvider('provideSchemaFiles')]
    public function testImportsWithoutDeprecatedDbalApi(string $yamlSchemaDefinitionFile): void
    {
        Deprecation::disable();
        Deprecation::enableTrackingDeprecations();

        try {
            (new SchemaImporter())->importFromFile(__DIR__ . "/_fixtures/{$yamlSchemaDefinitionFile}");
            $triggeredDeprecations = array_filter(Deprecation::getTriggeredDeprecations());
        } finally {
            Deprecation::disable();
        }

        self::assertSame([], $triggeredDeprecations);
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideSchemaFiles(): iterable
    {
        yield 'composite primary key' => ['02-composite_pk_with_ai.yaml'];
        yield 'table foreign key' => ['03-foreign_key.yaml'];
        yield 'table indexes' => ['06-index.yaml'];
        yield 'index prefix lengths' => ['index-prefix-lengths.yaml'];
        yield 'column indexes and foreign keys' => ['simple-foreign-key.yaml'];
        yield 'platform options, values, partial indexes and deferrable foreign keys' => ['dbal-options.yaml'];
    }

    /**
     * @throws Exception
     */
    #[DataProvider('provideInvalidConfigurations')]
    public function testImportFailsOnInvalidConfiguration(
        string $schemaDefinition,
        string $expectedMessage
    ): void {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new SchemaImporter())->importFromSource($schemaDefinition);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'unhandled column option' => [
            'tables: { my_table: { fields: { data: { type: integer, options: { foo: true } } } } }',
            'Unhandled column option "foo" in schema configuration for "my_table.fields.data".',
        ];

        yield 'unhandled index option' => [
            'tables: { my_table: { fields: { data: { type: integer } },'
            . ' indexes: { ix_data: { fields: [data], options: { flags: [fulltext] } } } } }',
            'Unhandled property in schema configuration for "my_table.indexes.ix_data.options". "flags" keys are not'
            . ' allowed. Allowed keys: "lengths", "where".',
        ];

        yield 'unhandled foreign key option' => [
            'tables: { my_table: { fields: { data: { type: integer } }, foreignKeys: { fk_data: { fields: [data],'
            . ' foreignTable: other_table, foreignFields: [id], options: { foo: true } } } } }',
            'Unhandled property in schema configuration for "my_table.foreignKeys.fk_data.options". "foo" keys are not'
            . ' allowed. Allowed keys: "onDelete", "onUpdate", "deferrable", "deferred", "match".',
        ];

        yield 'unhandled column platform option' => [
            'tables: { my_table: { fields: { data: { type: json, options: { platformOptions: { jsonb: true } } } } } }',
            'Unhandled column platform option "jsonb" in schema configuration for'
            . ' "my_table.fields.data.options.platformOptions".',
        ];

        yield 'initially deferred foreign key that is not deferrable' => [
            'tables: { my_table: { fields: { data: { type: integer } }, foreignKeys: { fk_data: { fields: [data],'
            . ' foreignTable: other_table, foreignFields: [id], options: { deferrable: false, deferred: true } } } } }',
            'Invalid schema configuration for "my_table.foreignKeys.fk_data.options". A foreign key can\'t be initially'
            . ' deferred without being deferrable.',
        ];

        yield 'unknown match type' => [
            'tables: { my_table: { fields: { data: { type: integer } }, foreignKeys: { fk_data: { fields: [data],'
            . ' foreignTable: other_table, foreignFields: [id], options: { match: loose } } } } }',
            'Unhandled property in schema configuration for "my_table.foreignKeys.fk_data.options.match". Expected a'
            . ' match type, found string.',
        ];

        yield 'index on a missing column' => [
            'tables: { my_table: { fields: { data: { type: integer } },'
            . ' indexes: { ix_data: { fields: [data, missing] } } } }',
            'Invalid schema configuration for "my_table.indexes.ix_data.fields". There is no column with name'
            . ' "missing" in the table.',
        ];

        yield 'unique constraint on a missing column' => [
            'tables: { my_table: { id: { id: { type: integer } },'
            . ' uniqueConstraints: { ux_data: { fields: [missing] } } } }',
            'Invalid schema configuration for "my_table.uniqueConstraints.ux_data.fields". There is no column with name'
            . ' "missing" in the table.',
        ];

        yield 'invalid index name' => [
            'tables: { my_table: { fields: { data: { type: integer } },'
            . ' indexes: { "ix-data": { fields: [data] } } } }',
            'Unhandled property in schema configuration for "my_table.indexes.ix-data". Expected an index name of'
            . ' letters, digits and underscores, found "ix-data".',
        ];

        yield 'invalid column index name' => [
            'tables: { my_table: { fields: { data: { type: integer, index: { name: "ix data" } } } } }',
            'Unhandled property in schema configuration for "my_table.fields.data.index". Expected an index name of'
            . ' letters, digits and underscores, found "ix data".',
        ];
    }

    /**
     * @throws Exception
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
     * @throws Exception
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
     *
     * @throws Exception
     */
    #[Group('legacy')]
    public function testStringColumnWithoutLengthDefaultsTo255(): void
    {
        $importer = new SchemaImporter();

        $schema = $importer->importFromFile(__DIR__ . '/_fixtures/string-without-length.yaml');

        self::assertSame(255, $schema->getTable('my_table')->getColumn('name')->getLength());
    }
}
