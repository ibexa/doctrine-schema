<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Ibexa\Contracts\DoctrineSchema\Exception\MappingException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @internal
 */
class DoctrineSchemaMetadata implements DoctrineSchemaMetadataInterface
{
    private const INHERITANCE_TYPE_NONE = 1;
    private const INHERITANCE_TYPE_JOINED = 2;

    private const SUBCLASS_PREFIX_SEPARATOR = '_';

    private Connection $connection;

    /**
     * @var array<string, \Doctrine\DBAL\Types\Types::*|string>
     */
    private array $columnToTypesMap;

    /**
     * @var array<string>
     */
    private array $identifierColumns;

    /** @phpstan-var self::INHERITANCE_* */
    private int $inheritanceType = self::INHERITANCE_TYPE_NONE;

    /**
     * @var array<string, \Ibexa\Contracts\DoctrineSchema\Gateway\DoctrineSchemaMetadataInterface>
     */
    private array $discriminatorMap = [];

    /**
     * @var array<non-empty-string, \Ibexa\Contracts\DoctrineSchema\Gateway\DoctrineRelationshipInterface>
     */
    private array $relationshipColumns = [];

    /** @var class-string|null */
    private ?string $className;

    /** @var non-empty-string */
    private string $tableName;

    private ?DoctrineSchemaMetadataInterface $parentMetadata = null;

    private ?TranslationDoctrineSchemaMetadataInterface $translationMetadata = null;

    /** @var array<string,\Doctrine\DBAL\Types\Type> */
    private array $columnTypeCache = [];

    /**
     * @phpstan-param array<string, \Doctrine\DBAL\Types\Types::*|string> $columnToTypesMap
     *
     * @param class-string|null $className class of objects to register metadata to in registry. Highly recommended
     *        to allow traversal of metadata when relations are used, for example. Null should be supplied only
     *        if a dedicated class does not exist, for example for Content field types external storage.
     * @param non-empty-string $tableName
     * @param array<string> $identifierColumns
     */
    public function __construct(
        Connection $connection,
        ?string $className,
        string $tableName,
        array $columnToTypesMap,
        array $identifierColumns
    ) {
        $this->connection = $connection;
        $this->className = $className;
        $this->tableName = $tableName;
        $this->columnToTypesMap = $columnToTypesMap;
        $this->identifierColumns = $identifierColumns;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return class-string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getColumnType(string $column): Type
    {
        if (!isset($this->columnTypeCache[$column])) {
            $this->columnTypeCache[$column] = $this->resolveColumnType($column);
        }

        return $this->columnTypeCache[$column];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    private function resolveColumnType(string $column): Type
    {
        if (isset($this->columnToTypesMap[$column])) {
            return Type::getType($this->columnToTypesMap[$column]);
        }

        if (isset($this->translationMetadata) && $this->translationMetadata->hasColumn($column)) {
            return $this->translationMetadata->getColumnType($column);
        }

        foreach ($this->discriminatorMap as $discriminator => $subclassMetadata) {
            if ($subclassMetadata->hasColumn($column)) {
                return $subclassMetadata->getColumnType($column);
            }

            if (strpos($column, $discriminator . self::SUBCLASS_PREFIX_SEPARATOR) === 0) {
                $tempColumn = substr($column, strlen($discriminator) + 1);

                if ($subclassMetadata->hasColumn($tempColumn)) {
                    return $subclassMetadata->getColumnType($tempColumn);
                }
            }
        }

        $availableColumns = array_keys($this->columnToTypesMap);

        foreach ($this->discriminatorMap as $discriminator => $subclassMetadata) {
            $inheritanceColumns = array_map(
                static fn (string $column): string => $discriminator . self::SUBCLASS_PREFIX_SEPARATOR . $column . '(inheritance)',
                $subclassMetadata->getColumns(),
            );
            $availableColumns = array_merge(
                $availableColumns,
                $inheritanceColumns,
            );
        }

        if (isset($this->translationMetadata)) {
            $translationColumns = array_map(
                static fn (string $column): string => $column . '(translation)',
                $this->translationMetadata->getColumns(),
            );
            $availableColumns = array_merge(
                $availableColumns,
                $translationColumns,
            );
        }

        throw new InvalidArgumentException(sprintf(
            'Column "%s" does not exist in "%s" table. Available columns: "%s"',
            $column,
            $this->getTableName(),
            implode('", "', $availableColumns),
        ));
    }

    public function getColumns(): array
    {
        return array_keys($this->columnToTypesMap);
    }

    public function hasColumn(string $column): bool
    {
        return isset($this->columnToTypesMap[$column]);
    }

    public function getColumn(string $column): string
    {
        return $this->columnToTypesMap[$column];
    }

    public function getInheritanceMetadataWithColumn(string $column): ?DoctrineSchemaMetadataInterface
    {
        foreach ($this->discriminatorMap as $subclassMetadata) {
            if ($subclassMetadata->hasColumn($column)) {
                return $subclassMetadata;
            }
        }

        return null;
    }

    public function isInheritedColumn(string $column): bool
    {
        return $this->getInheritanceMetadataWithColumn($column) !== null;
    }

    /**
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    public function getIdentifierColumn(): string
    {
        if (count($this->identifierColumns) > 1) {
            throw MappingException::singleIdNotAllowedOnCompositePrimaryKey();
        }

        if (!isset($this->identifierColumns[0])) {
            throw MappingException::noIdDefined();
        }

        return $this->identifierColumns[0];
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function convertToPHPValues(array $data): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $result = [];
        foreach ($data as $columnName => $value) {
            $result[$columnName] = $this->getColumnType($columnName)->convertToPHPValue($value, $platform);
        }

        return $result;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function convertToDatabaseValues(array $data): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $result = [];
        foreach ($data as $columnName => $value) {
            $result[$columnName] = $this->getColumnType($columnName)->convertToDatabaseValue($value, $platform);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, int>
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getBindingTypesForData(array $data): array
    {
        $types = [];
        foreach ($data as $columnName => $value) {
            $types[$columnName] = $this->getBindingTypeForColumn($columnName);
        }

        return $types;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getBindingTypeForColumn(string $columnName): int
    {
        return $this->getColumnType($columnName)->getBindingType();
    }

    public function setTranslationSchemaMetadata(TranslationDoctrineSchemaMetadataInterface $translationMetadata): void
    {
        $this->translationMetadata = $translationMetadata;
    }

    public function hasTranslationSchemaMetadata(): bool
    {
        return isset($this->translationMetadata);
    }

    public function getTranslationSchemaMetadata(): TranslationDoctrineSchemaMetadataInterface
    {
        if (!isset($this->translationMetadata)) {
            throw new LogicException(sprintf(
                '%s does not contain translation metadata. Ensure that %1$s::%s has been called.',
                DoctrineSchemaMetadata::class,
                'setTranslationSchemaMetadata',
            ));
        }

        return $this->translationMetadata;
    }

    public function setParentMetadata(DoctrineSchemaMetadataInterface $parentMetadata): void
    {
        $this->parentMetadata = $parentMetadata;
    }

    public function getParentMetadata(): ?DoctrineSchemaMetadataInterface
    {
        return $this->parentMetadata;
    }

    public function isTranslatedColumn(string $column): bool
    {
        return isset($this->translationMetadata) && $this->translationMetadata->hasColumn($column);
    }

    public function getSubclassByDiscriminator(string $discriminator): DoctrineSchemaMetadataInterface
    {
        if (empty($this->discriminatorMap)) {
            throw new RuntimeException(sprintf(
                '"%s" is not registered as a subclass for table "%s". There are no registered subclasses',
                $discriminator,
                $this->getTableName(),
            ));
        }

        if (!isset($this->discriminatorMap[$discriminator])) {
            throw new RuntimeException(sprintf(
                '"%s" is not registered as a subclass for table "%s". Available discriminators: "%s"',
                $discriminator,
                $this->getTableName(),
                implode('", "', array_keys($this->discriminatorMap)),
            ));
        }

        return $this->discriminatorMap[$discriminator];
    }

    /**
     * @return array<string, \Ibexa\Contracts\DoctrineSchema\Gateway\DoctrineSchemaMetadataInterface>
     */
    public function getSubclasses(): array
    {
        return $this->discriminatorMap;
    }

    public function addSubclass(string $discriminator, DoctrineSchemaMetadataInterface $doctrineSchemaMetadata): void
    {
        $this->inheritanceType = self::INHERITANCE_TYPE_JOINED;
        if (isset($this->discriminatorMap[$discriminator])) {
            throw new LogicException(sprintf(
                '"%s" is already added as a discriminator for a subtype.',
                $discriminator,
            ));
        }
        $this->discriminatorMap[$discriminator] = $doctrineSchemaMetadata;
        $doctrineSchemaMetadata->setParentMetadata($this);
    }

    public function isInheritanceTypeJoined(): bool
    {
        return $this->inheritanceType === self::INHERITANCE_TYPE_JOINED;
    }

    public function addRelationship(DoctrineRelationshipInterface $relationship): void
    {
        $foreignProperty = $relationship->getForeignProperty();

        if (isset($this->relationshipColumns[$foreignProperty])) {
            throw new \LogicException(sprintf(
                '"%s" is already added as foreign property.',
                $foreignProperty,
            ));
        }

        $this->relationshipColumns[$foreignProperty] = $relationship;
    }

    public function getRelationships(): array
    {
        return $this->relationshipColumns;
    }

    public function getRelationshipByForeignKeyColumn(string $foreignProperty): DoctrineRelationshipInterface
    {
        if (!isset($this->relationshipColumns[$foreignProperty])) {
            throw new InvalidArgumentException(sprintf(
                '"%s" does not exist as a relationship for "%s" class metadata. Available relationship: "%s"',
                $foreignProperty,
                $this->className,
                implode('", "', array_keys($this->relationshipColumns)),
            ));
        }

        return $this->relationshipColumns[$foreignProperty];
    }
}
