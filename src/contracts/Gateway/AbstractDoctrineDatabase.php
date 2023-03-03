<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

use Doctrine\Common\Collections\Expr\Expression;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;

/**
 * @internal
 *
 * @template T of array
 *
 * @implements \Ibexa\Contracts\DoctrineSchema\Gateway\GatewayInterface<T>
 */
abstract class AbstractDoctrineDatabase implements GatewayInterface
{
    public const DISCRIMINATOR_SEPARATOR = '_';

    private const TRANSLATION_TABLE_ALIAS = 'translation';

    private DoctrineSchemaMetadataInterface $metadata;

    protected DoctrineSchemaMetadataRegistry $registry;

    protected Connection $connection;

    public function __construct(Connection $connection, DoctrineSchemaMetadataRegistry $registry)
    {
        $this->connection = $connection;
        $this->registry = $registry;
    }

    /**
     * @return non-empty-string
     */
    abstract protected function getTableName(): string;

    protected function getTableAlias(): string
    {
        return $this->getTableName();
    }

    abstract protected function buildMetadata(): DoctrineSchemaMetadataInterface;

    public function getMetadata(): DoctrineSchemaMetadataInterface
    {
        return $this->metadata ??= $this->buildMetadata();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function doInsert(array $data): int
    {
        $metadata = $this->getMetadata();
        $data = $metadata->convertToDatabaseValues($data);
        $types = $metadata->getBindingTypesForData($data);

        $this->connection->insert($metadata->getTableName(), $data, $types);

        return (int)$this->connection->lastInsertId();
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function doDelete(array $criteria): void
    {
        $metadata = $this->getMetadata();
        $criteria = $metadata->convertToDatabaseValues($criteria);
        $types = $metadata->getBindingTypesForData($criteria);

        $this->connection->delete($metadata->getTableName(), $criteria, $types);
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function doUpdate(array $criteria, array $data): void
    {
        $metadata = $this->getMetadata();
        $criteria = $metadata->convertToDatabaseValues($criteria);
        $data = $metadata->convertToDatabaseValues($data);
        $types = $metadata->getBindingTypesForData($criteria + $data);

        $this->connection->update($metadata->getTableName(), $data, $criteria, $types);
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    public function countAll(): int
    {
        return $this->countBy([]);
    }

    /**
     * @param \Doctrine\Common\Collections\Expr\Expression|array<string, \Doctrine\Common\Collections\Expr\Expression|scalar|array<scalar>|null> $criteria
     *
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    public function countBy($criteria): int
    {
        $metadata = $this->getMetadata();
        $qb = $this->connection->createQueryBuilder();
        $this->applyInheritance($qb);

        $identifierColumn = $metadata->getIdentifierColumn();
        $tableAlias = $this->getTableAlias();
        $qb->select($this->connection->getDatabasePlatform()->getCountExpression($tableAlias . '.' . $identifierColumn));
        $qb->from($metadata->getTableName(), $tableAlias);

        $expr = $this->convertCriteriaToExpression($qb, $criteria);
        if ($expr !== null) {
            $qb->andWhere($expr);
        }

        return (int)$qb->execute()->fetchOne();
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findAll(?int $limit = null, int $offset = 0): array
    {
        return $this->findBy([], null, $limit, $offset);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findBy($criteria, ?array $orderBy = null, ?int $limit = null, int $offset = 0): array
    {
        // Columns have to be checked to prevent unsanitized user input
        $metadata = $this->getMetadata();

        $qb = $this->createBaseQueryBuilder();
        $this->applyInheritance($qb);

        foreach ($orderBy ?? [] as $column => $order) {
            if (!$metadata->hasColumn($column) && !$metadata->isInheritedColumn($column)) {
                $columns = $metadata->getColumns();
                foreach ($metadata->getSubclasses() as $subMetadata) {
                    $columns = array_merge($columns, $subMetadata->getColumns());
                }

                throw new InvalidArgumentException(sprintf(
                    '"%s" does not exist in "%s", or is not available for ordering. Available columns are: "%s"',
                    $column,
                    $this->getTableName(),
                    implode('", "', $columns),
                ));
            }

            if ($metadata->isInheritedColumn($column)) {
                $subMetadata = $metadata->getInheritanceMetadataWithColumn($column);
                assert($subMetadata !== null);
                $qb->addOrderBy($subMetadata->getTableName() . '.' . $column, $order);
            } else {
                $qb->addOrderBy($this->getTableAlias() . '.' . $column, $order);
            }
        }

        $expr = $this->convertCriteriaToExpression($qb, $criteria);
        if ($expr !== null) {
            $qb->andWhere($expr);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        $qb->setFirstResult($offset);
        $results = $qb->execute()->fetchAllAssociative();

        return array_map([$metadata, 'convertToPHPValues'], $results);
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?array
    {
        $result = $this->findBy($criteria, $orderBy, 1);

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    public function findById(int $id): ?array
    {
        $metadata = $this->getMetadata();
        $identifierColumn = $metadata->getIdentifierColumn();

        return $this->findOneBy([
            $identifierColumn => $id,
        ]);
    }

    /**
     * @return string[]
     */
    protected function getColumnsWithTablePrefix(?string $tableAlias = null): array
    {
        $tableAlias ??= $this->getTableAlias();

        return array_map(static function (string $column) use ($tableAlias): string {
            return $tableAlias . '.' . $column;
        }, $this->getMetadata()->getColumns());
    }

    protected function createBaseQueryBuilder(?string $tableAlias = null): QueryBuilder
    {
        $columns = $this->getColumnsWithTablePrefix($tableAlias);

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->from($this->getTableName(), $tableAlias ?? $this->getTableAlias())
            ->select(...$columns);

        return $qb;
    }

    private function applyInheritance(QueryBuilder $qb): void
    {
        $metadata = $this->getMetadata();

        if (!$metadata->isInheritanceTypeJoined()) {
            return;
        }

        $tables = [];
        foreach ($metadata->getSubclasses() as $discriminator => $subclassMetadata) {
            $tableName = $subclassMetadata->getTableName();

            $prefixedColumns = array_map(static function (string $column) use ($tableName, $discriminator): string {
                return $tableName . '.' . $column . ' AS ' . $discriminator . self::DISCRIMINATOR_SEPARATOR . $column;
            }, $subclassMetadata->getColumns());
            $qb->addSelect(...$prefixedColumns);

            if (in_array($tableName, $tables, true)) {
                continue;
            }

            $tables[] = $tableName;
            $qb->leftJoin(
                $this->getTableAlias(),
                $tableName,
                $tableName,
                $qb->expr()->eq(
                    $this->getTableAlias() . '.' . $metadata->getIdentifierColumn(),
                    $tableName . '.' . $subclassMetadata->getIdentifierColumn(),
                ),
            );
        }
    }

    /**
     * @param \Doctrine\Common\Collections\Expr\Expression|array<string, \Doctrine\Common\Collections\Expr\Expression|scalar|array<scalar>|null> $criteria
     *
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression|string|null
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    final protected function convertCriteriaToExpression(
        QueryBuilder $qb,
        $criteria
    ) {
        /** @var array<string> $conditions */
        $conditions = [];
        $visitor = $this->buildExpressionVisitor($qb);

        if ($criteria instanceof Expression) {
            return $this->buildConditionExpression($visitor, $qb, $criteria);
        }

        foreach ($criteria as $column => $value) {
            $conditions[] = $value instanceof Expression
                ? $this->buildConditionExpression($visitor, $qb, $value)
                : $this->buildCondition($qb, $column, $value);
        }

        if (empty($conditions)) {
            return null;
        }

        return $qb->expr()->and(...$conditions);
    }

    protected function buildExpressionVisitor(QueryBuilder $qb): ExpressionVisitor
    {
        return new ExpressionVisitor(
            $qb,
            $this->registry,
            $this->getTableName(),
            $this->getTableAlias(),
        );
    }

    private function buildConditionExpression(ExpressionVisitor $visitor, QueryBuilder $qb, Expression $value): string
    {
        $sql = $visitor->dispatch($value);

        foreach ($visitor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameterValue = $parameter->getValue();
            $qb->setParameter($parameter->getName(), $parameterValue, $type);
        }

        return (string) $sql;
    }

    /**
     * @param scalar|array<scalar>|null $value
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    private function buildCondition(QueryBuilder $qb, string $column, $value): string
    {
        $metadata = $this->getMetadata();
        $columnBinding = $metadata->getBindingTypeForColumn($column);

        if ($column !== $metadata->getIdentifierColumn() && $metadata->isTranslatedColumn($column)) {
            $translationMetadata = $metadata->getTranslationSchemaMetadata();
            $translationForeignKeyColumn = $translationMetadata->getTranslatableIdColumn();

            $subquery = $this->connection->createQueryBuilder();
            $subquery->select(self::TRANSLATION_TABLE_ALIAS . '.' . $translationForeignKeyColumn);
            $subquery->from($translationMetadata->getTableName(), self::TRANSLATION_TABLE_ALIAS);
            $subquery->where($subquery->expr()->eq(
                self::TRANSLATION_TABLE_ALIAS . '.' . $translationForeignKeyColumn,
                $this->getTableAlias() . '.' . $metadata->getIdentifierColumn(),
            ));

            $fullColumnName = self::TRANSLATION_TABLE_ALIAS . '.' . $column;
            if ($value === null) {
                $subquery->andWhere($subquery->expr()->isNull($fullColumnName));
            } elseif (is_array($value)) {
                $parameter = $qb->createPositionalParameter(
                    $value,
                    $columnBinding + Connection::ARRAY_PARAM_OFFSET
                );

                $subquery->andWhere($qb->expr()->in($fullColumnName, $parameter));
            } else {
                $parameter = $qb->createPositionalParameter($value, $columnBinding);

                $subquery->andWhere($qb->expr()->eq($fullColumnName, $parameter));
            }

            return $qb->expr()->in(
                $this->getTableAlias() . '.' . $metadata->getIdentifierColumn(),
                $subquery->getSQL(),
            );
        }

        if ($column !== $metadata->getIdentifierColumn() && $metadata->isInheritedColumn($column)) {
            $subMetadata = $metadata->getInheritanceMetadataWithColumn($column);
            assert($subMetadata !== null);

            $fullColumnName = $subMetadata->getTableName() . '.' . $column;
        } else {
            $fullColumnName = $this->getTableAlias() . '.' . $column;
        }

        if ($value === null) {
            return $qb->expr()->isNull($fullColumnName);
        }

        if (is_array($value)) {
            $parameter = $qb->createPositionalParameter(
                $value,
                $columnBinding + Connection::ARRAY_PARAM_OFFSET
            );

            return $qb->expr()->in($fullColumnName, $parameter);
        }

        $parameter = $qb->createPositionalParameter($value, $columnBinding);

        return $qb->expr()->eq($fullColumnName, $parameter);
    }
}
