<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Importer;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
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
     * @throws \Doctrine\DBAL\DBALException
     */
    public function providerForTestImportFromFile(): iterable
    {
        yield from [
            0 => [
                '00-simple_pk.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column('id', Type::getType('integer')))->setAutoincrement(
                                    true
                                ),
                            ],
                            [
                                new Index('primary', ['id'], false, true),
                            ]
                        ),
                    ]
                ),
            ],
            1 => [
                '01-composite_pk.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column('id', Type::getType('integer')))->setDefault(0),
                                (new Column('version', Type::getType('integer')))->setDefault(0),
                                new Column('name', Type::getType('string')),
                            ],
                            [
                                new Index('primary', ['id', 'version'], false, true),
                            ]
                        ),
                    ]
                ),
            ],
            2 => [
                '02-composite_pk_with_ai.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column('id', Type::getType('integer')))
                                    ->setAutoincrement(true),
                                (new Column('version', Type::getType('integer')))->setDefault(0),
                                new Column('name', Type::getType('string')),
                            ],
                            [
                                new Index('primary', ['id', 'version'], false, true),
                            ]
                        ),
                    ]
                ),
            ],
            3 => [
                '03-foreign_key.yaml',
                new Schema(
                    [
                        new Table(
                            'my_main_table',
                            [
                                (new Column('id', Type::getType('integer')))
                                    ->setAutoincrement(true),
                                new Column('name', Type::getType('string')),
                            ],
                            [
                                new Index('primary', ['id'], false, true),
                            ]
                        ),
                        new Table(
                            'my_secondary_table',
                            [
                                (new Column('id', Type::getType('integer')))
                                    ->setAutoincrement(true),
                                new Column('main_id', Type::getType('integer')),
                            ],
                            [
                                new Index('primary', ['id'], false, true),
                            ],
                            [
                                new ForeignKeyConstraint(
                                    ['main_id'],
                                    'my_main_table',
                                    ['id'],
                                    'fk_my_secondary_table_id_main',
                                    ['onDelete' => 'CASCADE', 'onUpdate' => 'CASCADE']
                                ),
                            ]
                        ),
                    ]
                ),
            ],
            4 => [
                '04-nullable_field.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column('data', Type::getType('integer')))->setNotnull(false),
                            ]
                        ),
                    ]
                ),
            ],
            5 => [
                '05-varchar_length.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column('name', Type::getType('string')))->setLength(64),
                            ]
                        ),
                    ]
                ),
            ],
            6 => [
                '06-index.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                new Column('data1', Type::getType('integer')),
                                new Column('data2', Type::getType('integer')),
                                new Column('name', Type::getType('string')),
                            ],
                            [
                                new Index('ix_simple', ['data1'], false, false),
                                new Index('ix_composite', ['data1', 'data2'], false, false),
                                new Index('ux_name', ['name'], true, false),
                            ]
                        ),
                    ]
                ),
            ],
            7 => [
                '07-numeric-options.yaml',
                new Schema(
                    [
                        new Table(
                            'my_table',
                            [
                                (new Column(
                                    'data',
                                    Type::getType('decimal')
                                )
                                )->setPrecision(19)->setScale(4),
                            ]
                        ),
                    ]
                ),
            ],
        ];

        yield [
            'simple-field-index.yaml',
            new Schema(
                [
                    new Table(
                        'my_table',
                        [
                            new Column('data1', Type::getType('integer')),
                            new Column('data2', Type::getType('integer')),
                            new Column('data3', Type::getType('string')),
                        ],
                        [
                            new Index('data1_idx', ['data1'], false, false),
                            new Index('data2_idx', ['data2'], false, false),
                            new Index('data3_uidx', ['data3'], true, false),
                        ],
                    ),
                ]
            ),
        ];

        $table = new Table(
            'my_table',
            [
                new Column('id', Type::getType('integer')),
                new Column('data1', Type::getType('integer')),
                new Column('data2', Type::getType('integer')),
                new Column('data3', Type::getType('string')),
                new Column('data4', Type::getType('string')),
            ],
            [
                // Index for data1 is intentionally omitted
                new Index('data2_idx', ['data2'], false, false),
                new Index('data3_idx', ['data3'], false, false),
                new Index('data4_uidx', ['data4'], true, false),
            ],
            [
                new ForeignKeyConstraint(
                    ['id'],
                    'foreign_table_id',
                    ['foreign_id'],
                    'id_fk',
                ),
                new ForeignKeyConstraint(
                    ['data1'],
                    'foreign_table_1',
                    ['foreign_data1'],
                    'FK_9AEF3D8257CA2CA6', // Autogenerated
                ),
                new ForeignKeyConstraint(
                    ['data2'],
                    'foreign_table_2',
                    ['foreign_data2'],
                    'foreign_data2_fk_name',
                ),
                new ForeignKeyConstraint(
                    ['data3'],
                    'foreign_table_3',
                    ['foreign_data3'],
                    'foreign_data3_fk_name',
                ),
                new ForeignKeyConstraint(
                    ['data4'],
                    'foreign_table_4',
                    ['foreign_data4'],
                    'foreign_data4_fk_name',
                    [
                        'onDelete' => 'CASCADE',
                        'onUpdate' => 'RESTRICT',
                    ],
                ),
            ]
        );
        $table->setPrimaryKey(['id']);

        yield [
            'simple-foreign-key.yaml',
            new Schema(
                [
                    $table,
                ]
            ),
        ];
    }

    /**
     * @dataProvider providerForTestImportFromFile
     *
     * @param string $yamlSchemaDefinitionFile custom Yaml schema definition fixture file name
     *
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     * @throws \Doctrine\DBAL\DBALException
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
}
