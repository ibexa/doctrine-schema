<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Validator\Mapping\Loader;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\StringType;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaColumn;
use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaTable;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\Mapping\Loader\AutoMappingTrait;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * Turns the schema columns mapped with {@see SchemaColumn} and {@see SchemaTable} into validation constraints.
 *
 * @internal
 */
final class SchemaLoader implements LoaderInterface
{
    use AutoMappingTrait;

    private const array INTEGER_RANGES = [
        SmallIntType::class => [[-32768, 32767], [0, 65535]],
        IntegerType::class => [[-2147483648, 2147483647], [0, 4294967295]],
        BigIntType::class => [[null, null], [0, null]],
    ];

    private ?Schema $schema = null;

    public function __construct(
        private readonly SchemaBuilderInterface $schemaBuilder,
        private readonly ?string $classValidatorRegexp = null,
    ) {}

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $tableName = $this->getMappedTableName($metadata);
        $isClassAutoMapped = $tableName !== null
            && $this->isAutoMappingEnabledForClass($metadata, $this->classValidatorRegexp);

        $loaded = false;
        foreach ($this->getPropertyNames($metadata, $tableName !== null) as $propertyName) {
            $column = $this->resolveColumn($metadata, $propertyName, $tableName, $isClassAutoMapped);
            if ($column !== null) {
                $loaded = $this->addColumnConstraints($metadata, $propertyName, $column[0], $column[1]) || $loaded;
            }
        }

        return $loaded;
    }

    private function getMappedTableName(ClassMetadata $metadata): ?string
    {
        foreach ($metadata->getConstraints() as $constraint) {
            if ($constraint instanceof SchemaTable) {
                return $constraint->name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getPropertyNames(
        ClassMetadata $metadata,
        bool $includeUnconstrained
    ): array {
        $propertyNames = $metadata->getConstrainedProperties();
        if ($includeUnconstrained) {
            foreach ($metadata->getReflectionClass()->getProperties() as $property) {
                if (!$property->isStatic()) {
                    $propertyNames[] = $property->getName();
                }
            }
        }

        return array_values(array_unique($propertyNames));
    }

    /**
     * @return array{Column, array<string>|null}|null
     */
    private function resolveColumn(
        ClassMetadata $metadata,
        string $propertyName,
        ?string $tableName,
        bool $isClassAutoMapped
    ): ?array {
        $marker = $this->findPropertyConstraint($metadata, $propertyName, SchemaColumn::class);
        if ($marker !== null) {
            return [
                $this->getColumn(
                    $metadata->getClassName(),
                    $propertyName,
                    $marker->table ?? $tableName,
                    $marker->column ?? self::toColumnName($propertyName)
                ),
                $marker->groups,
            ];
        }

        if ($tableName === null || !$this->isPropertyAutoMapped($metadata, $propertyName, $isClassAutoMapped)) {
            return null;
        }

        $column = $this->findAutoMappedColumn(
            $metadata->getClassName(),
            $tableName,
            self::toColumnName($propertyName)
        );

        return $column !== null ? [$column, null] : null;
    }

    private function isPropertyAutoMapped(
        ClassMetadata $metadata,
        string $propertyName,
        bool $isClassAutoMapped
    ): bool {
        $isAutoMapped = $isClassAutoMapped;
        foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
            if (!$propertyMetadata instanceof GenericMetadata) {
                continue;
            }

            $strategy = $propertyMetadata->getAutoMappingStrategy();
            if ($strategy !== AutoMappingStrategy::NONE) {
                $isAutoMapped = $strategy === AutoMappingStrategy::ENABLED;
            }
        }

        return $isAutoMapped;
    }

    private function findAutoMappedColumn(
        string $className,
        string $tableName,
        string $columnName
    ): ?Column {
        $schema = $this->getSchema();
        if (!$schema->hasTable($tableName)) {
            throw new MappingException(sprintf(
                'Table "%s" mapped to "%s" does not exist in the schema.',
                $tableName,
                $className
            ));
        }

        $table = $schema->getTable($tableName);

        return $table->hasColumn($columnName) ? $table->getColumn($columnName) : null;
    }

    private function getColumn(
        string $className,
        string $propertyName,
        ?string $tableName,
        string $columnName
    ): Column {
        if ($tableName === null) {
            throw new MappingException(sprintf(
                'The schema column of "%s::$%s" has no table: set it on the SchemaColumn constraint, or add a SchemaTable constraint to the class.',
                $className,
                $propertyName
            ));
        }

        $schema = $this->getSchema();
        if (!$schema->hasTable($tableName) || !$schema->getTable($tableName)->hasColumn($columnName)) {
            throw new MappingException(sprintf(
                'Column "%s.%s" mapped to "%s::$%s" does not exist in the schema.',
                $tableName,
                $columnName,
                $className,
                $propertyName
            ));
        }

        return $schema->getTable($tableName)->getColumn($columnName);
    }

    /**
     * @param array<string>|null $groups
     */
    private function addColumnConstraints(
        ClassMetadata $metadata,
        string $propertyName,
        Column $column,
        ?array $groups
    ): bool {
        $type = $column->getType();
        if ($type instanceof StringType) {
            return $this->addLengthConstraint($metadata, $propertyName, $column->getLength(), $groups);
        }

        foreach (self::INTEGER_RANGES as $typeClass => $ranges) {
            if ($type instanceof $typeClass) {
                [$min, $max] = $ranges[$column->getUnsigned() ? 1 : 0];

                return $this->addRangeConstraint($metadata, $propertyName, $min, $max, $groups);
            }
        }

        return false;
    }

    /**
     * @param array<string>|null $groups
     */
    private function addLengthConstraint(
        ClassMetadata $metadata,
        string $propertyName,
        ?int $length,
        ?array $groups
    ): bool {
        if ($length === null || $length <= 0) {
            return false;
        }

        $existingConstraint = $this->findPropertyConstraint($metadata, $propertyName, Length::class);
        if ($existingConstraint === null) {
            $metadata->addPropertyConstraint($propertyName, new Length(max: $length, groups: $groups));

            return true;
        }

        if ($existingConstraint->max === null) {
            $existingConstraint->max = $length;

            return true;
        }

        return false;
    }

    /**
     * @param array<string>|null $groups
     */
    private function addRangeConstraint(
        ClassMetadata $metadata,
        string $propertyName,
        ?int $min,
        ?int $max,
        ?array $groups
    ): bool {
        if (($min === null && $max === null)
            || $this->findPropertyConstraint($metadata, $propertyName, Range::class) !== null
        ) {
            return false;
        }

        $metadata->addPropertyConstraint($propertyName, new Range(min: $min, max: $max, groups: $groups));

        return true;
    }

    /**
     * @template T of \Symfony\Component\Validator\Constraint
     *
     * @param class-string<T> $constraintClass
     *
     * @return T|null
     */
    private function findPropertyConstraint(
        ClassMetadata $metadata,
        string $propertyName,
        string $constraintClass
    ): ?object {
        foreach ($metadata->getPropertyMetadata($propertyName) as $propertyMetadata) {
            foreach ($propertyMetadata->getConstraints() as $constraint) {
                if ($constraint instanceof $constraintClass) {
                    return $constraint;
                }
            }
        }

        return null;
    }

    private function getSchema(): Schema
    {
        return $this->schema ??= $this->schemaBuilder->buildSchema();
    }

    private static function toColumnName(string $propertyName): string
    {
        return strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $propertyName));
    }
}
