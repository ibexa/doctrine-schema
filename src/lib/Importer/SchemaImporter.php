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
 * @see \Doctrine\DBAL\Schema\Schema
 */
class SchemaImporter implements APISchemaImporter
{
    /**
     * {@inheritdoc}
     */
    public function importFromFile(string $schemaFilePath, ?Schema $targetSchema = null): Schema
    {
        return $this->importFromArray(
            Yaml::parseFile($schemaFilePath),
            $targetSchema
        );
    }

    /**
     * {@inheritdoc}
     */
    public function importFromSource(string $schemaDefinition, ?Schema $targetSchema = null): Schema
    {
        return $this->importFromArray(
            Yaml::parse($schemaDefinition),
            $targetSchema
        );
    }

    /**
     * Import schema described by array loaded from Yaml custom format to the currently configured database.
     */
    private function importFromArray(array $schemaDefinition, ?Schema $targetSchema = null): Schema
    {
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
     * @param \Doctrine\DBAL\Schema\Schema target schema
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
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     */
    private function addSchemaTableColumns(Table $table, array $columnList): void
    {
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
        }
    }

    private function ensureNoExtraKeys(array $tableConfiguration, string $location, array $allowedKeys): void
    {
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
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     */
    private function addIndexToColumn(array $indexConfig, string $location, Table $table, string $columnName): void
    {
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
     * @phpstan-param string|array<string, mixed> $indexConfig
     *
     * @phpstan-return array<string, {
     *     name: string,
     *     unique?: bool,
     *     options?: array<mixed>,
     * }>
     *
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     */
    private function normalizeIndexConfig($indexConfig, string $location): array
    {
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
