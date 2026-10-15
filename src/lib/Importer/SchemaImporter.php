<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Importer;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface as APISchemaImporter;
use Symfony\Component\Yaml\Yaml;

/**
 * Import database schema from custom Yaml Doctrine Schema format into Schema object.
 *
 * @phpstan-type TColumnIndexConfigurationData string|array{name: string, unique?: bool, options?: array<string, mixed>}
 * @phpstan-type TIndexConfigurationData array{fields: non-empty-list<string>, options?: array<string, mixed>}
 * @phpstan-type TForeignKeyIndexNameConfigurationData non-empty-string|array{
 *     name: non-empty-string,
 *     auto_derived?: false,
 * }|array{
 *     auto_derived: true,
 * }
 *
 * @see Schema
 */
class SchemaImporter implements APISchemaImporter
{
    private const FOREIGN_KEY_NAME_PREFIX = 'fk_';

    private const FOREIGN_KEY_NAME_SUFFIX = '_fk';

    private const INDEX_NAME_PREFIX = 'idx_';

    private const INDEX_NAME_SUFFIX = '_idx';

    /**
     * {@inheritdoc}
     */
    public function importFromFile(
        string $schemaFilePath,
        ?Schema $targetSchema = null
    ): Schema {
        return $this->importFromArray(
            Yaml::parseFile($schemaFilePath),
            $targetSchema
        );
    }

    /**
     * {@inheritdoc}
     */
    public function importFromSource(
        string $schemaDefinition,
        ?Schema $targetSchema = null
    ): Schema {
        return $this->importFromArray(
            Yaml::parse($schemaDefinition),
            $targetSchema
        );
    }

    /**
     * Import schema described by array loaded from Yaml custom format to the currently configured database.
     */
    private function importFromArray(
        array $schemaDefinition,
        ?Schema $targetSchema = null
    ): Schema {
        if (null === $targetSchema) {
            $targetSchema = new Schema();
        }

        foreach ($schemaDefinition['tables'] as $tableName => $tableConfiguration) {
            $this->importSchemaTable($targetSchema, $tableName, $tableConfiguration);
        }

        return $targetSchema;
    }

    /**
     * Import table from the given configuration to the given schema.
     *
     * @param Schema target schema
     */
    private function importSchemaTable(
        Schema $schema,
        string $tableName,
        array $tableConfiguration
    ): void {
        $table = $schema->createTable($tableName);

        $this->ensureNoExtraKeys($tableConfiguration, $tableName, [
            'id',
            'fields',
            'foreignKeys',
            'indexes',
            'uniqueConstraints',
        ]);

        if (isset($tableConfiguration['id'])) {
            $this->addSchemaTableColumns($table, $tableConfiguration['id']);
            $table->setPrimaryKey(array_keys($tableConfiguration['id']));
        }

        if (isset($tableConfiguration['fields'])) {
            $this->addSchemaTableColumns($table, $tableConfiguration['fields']);
        }

        if (isset($tableConfiguration['foreignKeys'])) {
            foreach ($tableConfiguration['foreignKeys'] as $foreignKeyName => $foreignKey) {
                if (!is_string($foreignKeyName)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Invalid schema configuration for "%s.foreignKeys". Expected the foreign key name to be a string, found %s.',
                        $tableName,
                        get_debug_type($foreignKeyName),
                    ));
                }

                if (isset($foreignKey['indexName'])) {
                    $this->addForeignKeyIndex(
                        $table,
                        $tableConfiguration,
                        $foreignKeyName,
                        $foreignKey['fields'],
                        $foreignKey['indexName']
                    );
                }

                $table->addForeignKeyConstraint(
                    $foreignKey['foreignTable'],
                    $foreignKey['fields'],
                    $foreignKey['foreignFields'],
                    $foreignKey['options'] ?? [],
                    $foreignKeyName
                );
            }
        }

        if (isset($tableConfiguration['indexes'])) {
            foreach ($tableConfiguration['indexes'] as $indexName => $index) {
                $table->addIndex(
                    $index['fields'],
                    $indexName,
                    [],
                    $index['options'] ?? []
                );
            }
        }

        if (isset($tableConfiguration['uniqueConstraints'])) {
            foreach ($tableConfiguration['uniqueConstraints'] as $indexName => $index) {
                $table->addUniqueIndex(
                    $index['fields'],
                    $indexName,
                    $index['options'] ?? []
                );
            }
        }
    }

    /**
     * Adds columns to the given $table.
     *
     * @param array $columnList list of columns with their configuration
     *
     * @throws InvalidConfigurationException
     */
    private function addSchemaTableColumns(
        Table $table,
        array $columnList
    ): void {
        foreach ($columnList as $columnName => $columnConfiguration) {
            $location = sprintf('%s.fields.%s', $table->getName(), $columnName);
            $this->ensureNoExtraKeys($columnConfiguration, $location, [
                'length',
                'scale',
                'precision',
                'type',
                'nullable',
                'options',
                'index',
                'foreignKey',
            ]);

            if (isset($columnConfiguration['length'])) {
                $columnConfiguration['options']['length'] = $columnConfiguration['length'];
            }

            if (isset($columnConfiguration['scale'])) {
                $columnConfiguration['options']['scale'] = $columnConfiguration['scale'];
            }

            if (isset($columnConfiguration['precision'])) {
                $columnConfiguration['options']['precision'] = $columnConfiguration['precision'];
            }

            $column = $table->addColumn(
                $columnName,
                $columnConfiguration['type'],
                $columnConfiguration['options'] ?? []
            );

            if (isset($columnConfiguration['nullable'])) {
                $column->setNotnull(!$columnConfiguration['nullable']);
            }

            if (isset($columnConfiguration['index'])) {
                $indexConfig = $this->normalizeIndexConfig($columnConfiguration['index'], $location);

                $this->addIndexToColumn(
                    $indexConfig,
                    $location . '.index',
                    $table,
                    $column->getName(),
                );
            }

            if (isset($columnConfiguration['foreignKey'])) {
                $foreignKeyConfig = $columnConfiguration['foreignKey'];
                $foreignField = $foreignKeyConfig['field'];

                $table->addForeignKeyConstraint(
                    $foreignKeyConfig['table'],
                    [$column->getName()],
                    [$foreignField],
                    $foreignKeyConfig['options'] ?? [],
                    $foreignKeyConfig['name'] ?? null,
                );
            }
        }
    }

    /**
     * Adds the index backing a foreign key under the name its "indexName" gives, before the foreign key itself,
     * so that Doctrine DBAL doesn't generate one named after a hash.
     *
     * @phpstan-param array{
     *     id?: array<string, array{index?: TColumnIndexConfigurationData}>,
     *     fields?: array<string, array{index?: TColumnIndexConfigurationData}>,
     *     indexes?: array<string, TIndexConfigurationData>,
     *     uniqueConstraints?: array<string, TIndexConfigurationData>,
     * } $tableConfiguration
     * @phpstan-param non-empty-list<string> $columns the columns of the foreign key
     * @phpstan-param TForeignKeyIndexNameConfigurationData $indexNameConfig
     *
     * @throws InvalidConfigurationException
     */
    private function addForeignKeyIndex(
        Table $table,
        array $tableConfiguration,
        string $foreignKeyName,
        array $columns,
        $indexNameConfig
    ): void {
        $location = sprintf('%s.foreignKeys.%s.indexName', $table->getName(), $foreignKeyName);
        $indexName = $this->resolveForeignKeyIndexName($indexNameConfig, $foreignKeyName, $location);

        // Only declared indexes count: Doctrine DBAL drops the one it generated for another foreign key on the same
        // columns when this one is added.
        $indexedColumns = array_merge(
            array_column($tableConfiguration['indexes'] ?? [], 'fields'),
            array_column($tableConfiguration['uniqueConstraints'] ?? [], 'fields')
        );
        foreach (($tableConfiguration['id'] ?? []) + ($tableConfiguration['fields'] ?? []) as $columnName => $column) {
            if (isset($column['index'])) {
                $indexedColumns[] = [$columnName];
            }
        }
        if (in_array($columns, $indexedColumns, true)) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid schema configuration for "%s". Another index already covers the "%s" columns of the foreign key.',
                $location,
                implode('", "', $columns),
            ));
        }

        $table->addIndex($columns, $indexName);
    }

    /**
     * A derived name turns the "fk_" prefix of the foreign key name into "idx_", or else its "_fk" suffix into "_idx".
     *
     * @phpstan-param TForeignKeyIndexNameConfigurationData $indexNameConfig
     *
     * @phpstan-return non-empty-string
     *
     * @throws InvalidConfigurationException
     */
    private function resolveForeignKeyIndexName(
        $indexNameConfig,
        string $foreignKeyName,
        string $location
    ): string {
        if (is_string($indexNameConfig)) {
            $indexNameConfig = ['name' => $indexNameConfig];
        }

        if (!is_array($indexNameConfig)) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected a string or an array, found %s.',
                $location,
                get_debug_type($indexNameConfig),
            ));
        }

        $this->ensureNoExtraKeys($indexNameConfig, $location, ['name', 'auto_derived']);

        $isAutoDerived = $indexNameConfig['auto_derived'] ?? false;
        if (isset($indexNameConfig['name']) && $isAutoDerived) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid schema configuration for "%s". "name" and "auto_derived" are mutually exclusive.',
                $location,
            ));
        }

        if (isset($indexNameConfig['name'])) {
            if (!is_string($indexNameConfig['name']) || $indexNameConfig['name'] === '') {
                throw new InvalidConfigurationException(sprintf(
                    'Unhandled property in schema configuration for "%s". Expected "name" to be a non-empty string, found %s.',
                    $location,
                    get_debug_type($indexNameConfig['name']),
                ));
            }

            return $indexNameConfig['name'];
        }

        if ($isAutoDerived !== true) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid schema configuration for "%s". Expected either "name" or "auto_derived: true".',
                $location,
            ));
        }

        if (strpos($foreignKeyName, self::FOREIGN_KEY_NAME_PREFIX) === 0) {
            return self::INDEX_NAME_PREFIX . substr($foreignKeyName, strlen(self::FOREIGN_KEY_NAME_PREFIX));
        }

        if (substr($foreignKeyName, -strlen(self::FOREIGN_KEY_NAME_SUFFIX)) === self::FOREIGN_KEY_NAME_SUFFIX) {
            return substr($foreignKeyName, 0, -strlen(self::FOREIGN_KEY_NAME_SUFFIX)) . self::INDEX_NAME_SUFFIX;
        }

        throw new InvalidConfigurationException(sprintf(
            'Invalid schema configuration for "%s". The index name can be derived only from a foreign key name'
            . ' starting with "%s" or ending with "%s", found "%s".',
            $location,
            self::FOREIGN_KEY_NAME_PREFIX,
            self::FOREIGN_KEY_NAME_SUFFIX,
            $foreignKeyName,
        ));
    }

    private function ensureNoExtraKeys(
        array $tableConfiguration,
        string $location,
        array $allowedKeys
    ): void {
        $diff = array_diff(array_keys($tableConfiguration), $allowedKeys);
        if (!empty($diff)) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". "%s" keys are not allowed. Allowed keys: "%s".',
                $location,
                implode('", "', $diff),
                implode('", "', $allowedKeys),
            ));
        }
    }

    /**
     * @phpstan-param array{
     *     name: string,
     *     unique?: bool,
     *     options?: array<mixed>,
     * } $indexConfig
     *
     * @throws InvalidConfigurationException
     */
    private function addIndexToColumn(
        array $indexConfig,
        string $location,
        Table $table,
        string $columnName
    ): void {
        if (!isset($indexConfig['name']) || !is_string($indexConfig['name'])) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected "name" to be a string, found %s.',
                $location,
                get_debug_type($indexConfig['name']),
            ));
        }

        $this->ensureNoExtraKeys($indexConfig, $location . '.index', [
            'name',
            'unique',
            'options',
        ]);

        if ($indexConfig['unique'] ?? false) {
            $table->addUniqueIndex([$columnName], $indexConfig['name'], $indexConfig['options'] ?? []);
        } else {
            $table->addIndex([$columnName], $indexConfig['name'], [], $indexConfig['options'] ?? []);
        }
    }

    /**
     * @phpstan-param string|array{
     *     name: string,
     *     unique?: bool,
     *     options: array<mixed>,
     * } $indexConfig
     *
     * @phpstan-return array{
     *     name: string,
     *     unique?: bool,
     *     options?: array<mixed>,
     * }
     *
     * @throws InvalidConfigurationException
     */
    private function normalizeIndexConfig(
        $indexConfig,
        string $location
    ): array {
        if (!is_string($indexConfig) && !is_array($indexConfig)) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected a string or an array, found %s.',
                $location . '.index',
                get_debug_type($indexConfig),
            ));
        }

        if (is_string($indexConfig)) {
            $indexConfig = [
                'name' => $indexConfig,
            ];
        }

        return $indexConfig;
    }
}

class_alias(SchemaImporter::class, 'EzSystems\DoctrineSchema\Importer\SchemaImporter');
