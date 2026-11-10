<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Importer;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\Deferrability;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\MatchType;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface as APISchemaImporter;
use Symfony\Component\Yaml\Yaml;

/**
 * Import database schema from custom Yaml Doctrine Schema format into Schema object.
 *
 * @phpstan-type TColumnConfigurationData array<string, mixed>
 * @phpstan-type TForeignKeyConfigurationData array{
 *     fields: non-empty-list<non-empty-string>,
 *     foreignTable: non-empty-string,
 *     foreignFields: non-empty-list<non-empty-string>,
 *     options?: array<string, mixed>
 * }
 * @phpstan-type TIndexConfigurationData array{
 *     fields: non-empty-list<non-empty-string>,
 *     options?: array<string, mixed>
 * }
 * @phpstan-type TTableConfigurationData array{
 *     id?: array<non-empty-string, TColumnConfigurationData>,
 *     fields?: array<non-empty-string, TColumnConfigurationData>,
 *     foreignKeys?: array<non-empty-string, TForeignKeyConfigurationData>,
 *     indexes?: array<non-empty-string, TIndexConfigurationData>,
 *     uniqueConstraints?: array<non-empty-string, TIndexConfigurationData>
 * }
 *
 * @see Schema
 */
class SchemaImporter implements APISchemaImporter
{
    private const int DEFAULT_STRING_LENGTH = 255;

    public function importFromFile(string $schemaFilePath): Schema
    {
        return $this->importFromArray(Yaml::parseFile($schemaFilePath));
    }

    public function importFromSource(string $schemaDefinition): Schema
    {
        return $this->importFromArray(Yaml::parse($schemaDefinition));
    }

    /**
     * Import schema described by array loaded from Yaml custom format to the currently configured database.
     *
     * @phpstan-param array{tables: array<non-empty-string, TTableConfigurationData>} $schemaDefinition
     *
     * @throws SchemaException
     * @throws InvalidConfigurationException
     */
    private function importFromArray(array $schemaDefinition): Schema
    {
        $schema = Schema::editor();
        foreach ($schemaDefinition['tables'] as $tableName => $tableConfiguration) {
            $schema->addTable($this->importSchemaTable($tableName, $tableConfiguration));
        }

        return $schema->create();
    }

    /**
     * Builds the table the given configuration describes.
     *
     * @param non-empty-string $tableName
     *
     * @phpstan-param TTableConfigurationData $tableConfiguration
     *
     * @throws InvalidConfigurationException
     * @throws SchemaException
     */
    private function importSchemaTable(
        string $tableName,
        array $tableConfiguration
    ): Table {
        $this->ensureNoExtraKeys($tableConfiguration, $tableName, [
            'id',
            'fields',
            'foreignKeys',
            'indexes',
            'uniqueConstraints',
        ]);

        $table = Table::editor()->setUnquotedName($tableName);
        $tableColumnNames = array_merge(
            array_keys($tableConfiguration['id'] ?? []),
            array_keys($tableConfiguration['fields'] ?? [])
        );

        if (isset($tableConfiguration['id'])) {
            $this->addSchemaTableColumns($table, $tableName, $tableConfiguration['id']);
            $primaryKeyColumns = array_keys($tableConfiguration['id']);
            if ($primaryKeyColumns !== []) {
                $table->setPrimaryKeyConstraint(
                    PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primaryKeyColumns)->create()
                );
            }
        }

        if (isset($tableConfiguration['fields'])) {
            $this->addSchemaTableColumns($table, $tableName, $tableConfiguration['fields']);
        }

        if (isset($tableConfiguration['foreignKeys'])) {
            foreach ($tableConfiguration['foreignKeys'] as $foreignKeyName => $foreignKey) {
                $table->addForeignKeyConstraint($this->createForeignKey(
                    $foreignKeyName,
                    $foreignKey['fields'],
                    $foreignKey['foreignTable'],
                    $foreignKey['foreignFields'],
                    $foreignKey['options'] ?? [],
                    sprintf('%s.foreignKeys.%s', $tableName, $foreignKeyName),
                ));
            }
        }

        if (isset($tableConfiguration['indexes'])) {
            foreach ($tableConfiguration['indexes'] as $indexName => $index) {
                $location = sprintf('%s.indexes.%s', $tableName, $indexName);
                $this->ensureColumnsExist($index['fields'], $tableColumnNames, $location . '.fields');
                $table->addIndex($this->createIndex(
                    $indexName,
                    IndexType::REGULAR,
                    $index['fields'],
                    $index['options'] ?? [],
                    $location,
                ));
            }
        }

        if (isset($tableConfiguration['uniqueConstraints'])) {
            foreach ($tableConfiguration['uniqueConstraints'] as $indexName => $index) {
                $location = sprintf('%s.uniqueConstraints.%s', $tableName, $indexName);
                $this->ensureColumnsExist($index['fields'], $tableColumnNames, $location . '.fields');
                $table->addIndex($this->createIndex(
                    $indexName,
                    IndexType::UNIQUE,
                    $index['fields'],
                    $index['options'] ?? [],
                    $location,
                ));
            }
        }

        return $table->create();
    }

    /**
     * Adds columns to the given $table.
     *
     * @phpstan-param array<non-empty-string, TColumnConfigurationData> $columnList list of columns with their configuration
     *
     * @param non-empty-string $tableName
     *
     * @throws SchemaException
     * @throws InvalidConfigurationException
     */
    private function addSchemaTableColumns(
        TableEditor $table,
        string $tableName,
        array $columnList
    ): void {
        foreach ($columnList as $columnName => $columnConfiguration) {
            $location = sprintf('%s.fields.%s', $tableName, $columnName);
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
            } elseif ('string' === $columnConfiguration['type']) {
                trigger_deprecation(
                    'ibexa/doctrine-schema',
                    '6.0.0',
                    'Not stating "length" for the string column "%s" is deprecated and will throw in 7.0. '
                    . 'DBAL 4 no longer assumes a length for a string column, so it has to be stated. '
                    . 'It currently defaults to %d.',
                    $location,
                    self::DEFAULT_STRING_LENGTH,
                );

                $columnConfiguration['options']['length'] = self::DEFAULT_STRING_LENGTH;
            }

            if (isset($columnConfiguration['scale'])) {
                $columnConfiguration['options']['scale'] = $columnConfiguration['scale'];
            }

            if (isset($columnConfiguration['precision'])) {
                $columnConfiguration['options']['precision'] = $columnConfiguration['precision'];
            }

            $column = Column::editor()
                ->setUnquotedName($columnName)
                ->setTypeName($columnConfiguration['type']);

            foreach ($columnConfiguration['options'] ?? [] as $option => $value) {
                $column = $this->setColumnOption($column, $option, $value, $location);
            }

            if (isset($columnConfiguration['nullable'])) {
                $column->setNotNull(!$columnConfiguration['nullable']);
            }

            $table->addColumn($column->create());

            if (isset($columnConfiguration['index'])) {
                $indexConfig = $this->normalizeIndexConfig($columnConfiguration['index'], $location);

                $this->addIndexToColumn(
                    $indexConfig,
                    $location . '.index',
                    $table,
                    $columnName,
                );
            }

            if (isset($columnConfiguration['foreignKey'])) {
                $foreignKeyConfig = $columnConfiguration['foreignKey'];

                $table->addForeignKeyConstraint($this->createForeignKey(
                    $foreignKeyConfig['name'] ?? $this->generateForeignKeyName($tableName, $columnName),
                    [$columnName],
                    $foreignKeyConfig['table'],
                    [$foreignKeyConfig['field']],
                    $foreignKeyConfig['options'] ?? [],
                    $location . '.foreignKey',
                ));
            }
        }
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function setColumnOption(
        ColumnEditor $column,
        string $option,
        mixed $value,
        string $location
    ): ColumnEditor {
        return match ($option) {
            'autoincrement' => $column->setAutoincrement($value),
            'columnDefinition' => $column->setColumnDefinition($value),
            'comment' => $column->setComment($value),
            'default' => $column->setDefaultValue($value),
            'fixed' => $column->setFixed($value),
            'length' => $column->setLength($value),
            'notnull' => $column->setNotNull($value),
            'platformOptions' => $this->setColumnPlatformOptions($column, $value, $location),
            'precision' => $column->setPrecision($value),
            'scale' => $column->setScale($value),
            'unsigned' => $column->setUnsigned($value),
            'values' => $column->setValues($value),
            default => throw new InvalidConfigurationException(sprintf(
                'Unhandled column option "%s" in schema configuration for "%s".',
                $option,
                $location,
            )),
        };
    }

    /**
     * Column::setPlatformOptions() took any platform option, DBAL's column editor has a method for each one it supports.
     *
     * @throws InvalidConfigurationException
     */
    private function setColumnPlatformOptions(
        ColumnEditor $column,
        mixed $platformOptions,
        string $location
    ): ColumnEditor {
        $location .= '.options.platformOptions';
        if (!is_array($platformOptions)) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected an array, found %s.',
                $location,
                get_debug_type($platformOptions),
            ));
        }

        foreach ($platformOptions as $option => $value) {
            $column = match ($option) {
                'charset' => $column->setCharset($value),
                'collation' => $column->setCollation($value),
                'default_constraint_name' => $column->setDefaultConstraintName($value),
                'enumType' => $column->setEnumType($value),
                'max' => $column->setMaximumValue($value),
                'min' => $column->setMinimumValue($value),
                default => throw new InvalidConfigurationException(sprintf(
                    'Unhandled column platform option "%s" in schema configuration for "%s".',
                    $option,
                    $location,
                )),
            };
        }

        return $column;
    }

    /**
     * The name Table::addForeignKeyConstraint() generated for an unnamed foreign key. DBAL 4 creates one without a
     * name, leaving it to the database, which would name it differently. DBAL cut the name to the maximum identifier
     * length, which a name of at most 19 characters never reaches.
     *
     * @return non-empty-string
     */
    private function generateForeignKeyName(
        string $tableName,
        string $columnName
    ): string {
        return strtoupper('fk_' . dechex(crc32($tableName)) . dechex(crc32($columnName)));
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-list<non-empty-string> $columnNames
     * @param non-empty-string $foreignTableName
     * @param non-empty-list<non-empty-string> $foreignColumnNames
     * @param array<string, mixed> $options
     *
     * @throws InvalidConfigurationException
     */
    private function createForeignKey(
        string $name,
        array $columnNames,
        string $foreignTableName,
        array $foreignColumnNames,
        array $options,
        string $location
    ): ForeignKeyConstraint {
        $this->ensureNoExtraKeys($options, $location . '.options', [
            'onDelete',
            'onUpdate',
            'deferrable',
            'deferred',
            'match',
        ]);

        $foreignKey = ForeignKeyConstraint::editor()
            ->setUnquotedName($name)
            ->setUnquotedReferencingColumnNames(...$columnNames)
            ->setUnquotedReferencedTableName($foreignTableName)
            ->setUnquotedReferencedColumnNames(...$foreignColumnNames);

        if (isset($options['onDelete'])) {
            $foreignKey->setOnDeleteAction($this->getReferentialAction($options['onDelete'], $location));
        }

        if (isset($options['onUpdate'])) {
            $foreignKey->setOnUpdateAction($this->getReferentialAction($options['onUpdate'], $location));
        }

        if (isset($options['deferrable']) || isset($options['deferred'])) {
            $foreignKey->setDeferrability($this->getDeferrability($options, $location));
        }

        if (isset($options['match'])) {
            $foreignKey->setMatchType($this->getMatchType($options['match'], $location));
        }

        return $foreignKey->create();
    }

    /**
     * A foreign key is deferrable when declared so, or when initially deferred, as for ForeignKeyConstraint.
     *
     * @param array<string, mixed> $options
     *
     * @throws InvalidConfigurationException
     */
    private function getDeferrability(
        array $options,
        string $location
    ): Deferrability {
        foreach (['deferrable', 'deferred'] as $option) {
            if (isset($options[$option]) && !is_bool($options[$option])) {
                throw new InvalidConfigurationException(sprintf(
                    'Unhandled property in schema configuration for "%s". Expected "%s" to be a boolean, found %s.',
                    $location . '.options',
                    $option,
                    get_debug_type($options[$option]),
                ));
            }
        }

        $isDeferred = $options['deferred'] ?? false;
        $isDeferrable = $options['deferrable'] ?? $isDeferred;
        if ($isDeferred && !$isDeferrable) {
            throw new InvalidConfigurationException(sprintf(
                'Invalid schema configuration for "%s". A foreign key can\'t be initially deferred without being'
                . ' deferrable.',
                $location . '.options',
            ));
        }

        if ($isDeferred) {
            return Deferrability::DEFERRED;
        }

        return $isDeferrable ? Deferrability::DEFERRABLE : Deferrability::NOT_DEFERRABLE;
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function getMatchType(
        mixed $matchType,
        string $location
    ): MatchType {
        $type = is_string($matchType) ? MatchType::tryFrom(strtoupper($matchType)) : null;
        if ($type === null) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected a match type, found %s.',
                $location . '.options.match',
                get_debug_type($matchType),
            ));
        }

        return $type;
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function getReferentialAction(
        mixed $action,
        string $location
    ): ReferentialAction {
        $referentialAction = is_string($action) ? ReferentialAction::tryFrom(strtoupper($action)) : null;
        if ($referentialAction === null) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected a referential action, found %s.',
                $location,
                get_debug_type($action),
            ));
        }

        return $referentialAction;
    }

    /**
     * Table::addIndex() checked that the indexed columns exist, DBAL's editors don't.
     *
     * @param non-empty-list<non-empty-string> $columnNames
     * @param list<non-empty-string> $tableColumnNames
     *
     * @throws InvalidConfigurationException
     */
    private function ensureColumnsExist(
        array $columnNames,
        array $tableColumnNames,
        string $location
    ): void {
        foreach ($columnNames as $columnName) {
            if (!in_array($columnName, $tableColumnNames, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid schema configuration for "%s". There is no column with name "%s" in the table.',
                    $location,
                    $columnName,
                ));
            }
        }
    }

    /**
     * Table::addIndex() rejected index names with other characters than letters, digits and underscores, DBAL's
     * editors don't.
     *
     * @param non-empty-string $name
     * @param non-empty-list<non-empty-string> $columnNames
     * @param array<string, mixed> $options
     *
     * @throws InvalidConfigurationException
     */
    private function createIndex(
        string $name,
        IndexType $type,
        array $columnNames,
        array $options,
        string $location
    ): Index {
        if (preg_match('/[^a-zA-Z0-9_]/', $name) === 1) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected an index name of letters, digits and'
                . ' underscores, found "%s".',
                $location,
                $name,
            ));
        }

        $this->ensureNoExtraKeys($options, $location . '.options', ['lengths', 'where']);
        $lengths = $options['lengths'] ?? [];
        if (!is_array($lengths)) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected "lengths" to be a list, found %s.',
                $location,
                get_debug_type($lengths),
            ));
        }

        $index = Index::editor()->setUnquotedName($name)->setType($type);
        if (isset($options['where'])) {
            if (!is_string($options['where']) || $options['where'] === '') {
                throw new InvalidConfigurationException(sprintf(
                    'Unhandled property in schema configuration for "%s". Expected "where" to be a non-empty string,'
                    . ' found %s.',
                    $location,
                    get_debug_type($options['where']),
                ));
            }

            // DBAL only creates partial indexes on PostgreSQL, elsewhere the index is created without its predicate
            $index->setPredicate($options['where']);
        }

        foreach ($columnNames as $position => $columnName) {
            $index->addUnquotedColumnName(
                $columnName,
                $this->getIndexedColumnLength($lengths[$position] ?? null, $location)
            );
        }

        return $index->create();
    }

    /**
     * Schema files give prefix lengths as strings as well, which DBAL deprecated.
     *
     * @return positive-int|null
     *
     * @throws InvalidConfigurationException
     */
    private function getIndexedColumnLength(
        mixed $length,
        string $location
    ): ?int {
        if ($length === null) {
            return null;
        }

        $integerLength = filter_var($length, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($integerLength === false) {
            throw new InvalidConfigurationException(sprintf(
                'Unhandled property in schema configuration for "%s". Expected a positive integer length, found %s.',
                $location,
                get_debug_type($length),
            ));
        }

        return $integerLength;
    }

    /**
     * @phpstan-param array<string, mixed> $tableConfiguration
     *
     * @param array<string> $allowedKeys
     *
     * @throws InvalidConfigurationException
     */
    private function ensureNoExtraKeys(
        array $tableConfiguration,
        string $location,
        array $allowedKeys
    ): void {
        $diff = array_diff(array_keys($tableConfiguration), $allowedKeys);
        if (!empty($diff)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Unhandled property in schema configuration for "%s". "%s" keys are not allowed. Allowed keys: "%s".',
                    $location,
                    implode('", "', $diff),
                    implode('", "', $allowedKeys),
                )
            );
        }
    }

    /**
     * @phpstan-param array{
     *     name: non-empty-string,
     *     unique?: bool,
     *     options?: array<string, mixed>,
     * } $indexConfig
     *
     * @param non-empty-string $columnName
     *
     * @throws InvalidConfigurationException
     */
    private function addIndexToColumn(
        array $indexConfig,
        string $location,
        TableEditor $table,
        string $columnName
    ): void {
        if (!isset($indexConfig['name']) || !is_string($indexConfig['name'])) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Unhandled property in schema configuration for "%s". Expected "name" to be a string, found %s.',
                    $location,
                    get_debug_type($indexConfig['name']),
                )
            );
        }

        $this->ensureNoExtraKeys($indexConfig, $location . '.index', [
            'name',
            'unique',
            'options',
        ]);

        $table->addIndex($this->createIndex(
            $indexConfig['name'],
            ($indexConfig['unique'] ?? false) ? IndexType::UNIQUE : IndexType::REGULAR,
            [$columnName],
            $indexConfig['options'] ?? [],
            $location,
        ));
    }

    /**
     * @phpstan-param non-empty-string|array{
     *     name: non-empty-string,
     *     unique?: bool,
     *     options?: array<string, mixed>,
     * } $indexConfig
     *
     * @phpstan-return array{
     *     name: non-empty-string,
     *     unique?: bool,
     *     options?: array<string, mixed>,
     * }
     *
     * @throws InvalidConfigurationException
     */
    private function normalizeIndexConfig(
        $indexConfig,
        string $location
    ): array {
        if (!is_string($indexConfig) && !is_array($indexConfig)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Unhandled property in schema configuration for "%s". Expected a string or an array, found %s.',
                    $location . '.index',
                    get_debug_type($indexConfig),
                )
            );
        }

        if (is_string($indexConfig)) {
            $indexConfig = [
                'name' => $indexConfig,
            ];
        }

        return $indexConfig;
    }
}
