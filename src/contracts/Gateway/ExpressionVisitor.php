<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor as BaseExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression as DBALCompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use RuntimeException;

/**
 * @internal
 *
 * Based on {@see \Doctrine\ORM\Query\QueryExpressionVisitor}.
 */
final class ExpressionVisitor extends BaseExpressionVisitor
{
    private const TRANSLATION_TABLE_ALIAS = 'translation';

    private QueryBuilder $queryBuilder;

    private DoctrineSchemaMetadataInterface $schemaMetadata;

    private string $tableAlias;

    /** @var list<\Ibexa\Contracts\DoctrineSchema\Gateway\Parameter> */
    private array $parameters = [];

    private DoctrineSchemaMetadataRegistry $registry;

    /**
     * @param non-empty-string $tableName
     */
    public function __construct(
        QueryBuilder $queryBuilder,
        DoctrineSchemaMetadataRegistry $registry,
        string $tableName,
        string $tableAlias
    ) {
        $this->queryBuilder = $queryBuilder;
        $this->schemaMetadata = $registry->getMetadataForTable($tableName);
        $this->tableAlias = $tableAlias;
        $this->registry = $registry;
    }

    /**
     * @return list<\Ibexa\Contracts\DoctrineSchema\Gateway\Parameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function clearParameters(): void
    {
        $this->parameters = [];
    }

    public function walkComparison(Comparison $comparison)
    {
        $column = $comparison->getField();

        if (str_contains($column, '.')) {
            [
                $foreignProperty,
                $foreignClassProperty,
            ] = explode('.', $column, 2);

            $relationship = $this->schemaMetadata->getRelationshipByForeignKeyColumn($foreignProperty);
            $relationshipMetadata = $this->registry->getMetadata($relationship->getRelationshipClass());
            if (!$relationshipMetadata->hasColumn($foreignClassProperty)) {
                throw new \InvalidArgumentException(sprintf(
                    '"%s" does not exist as available column on "%s" class schema metadata. Available columns: "%s".',
                    $foreignClassProperty,
                    $relationshipMetadata->getClassName(),
                    implode('", "', $relationshipMetadata->getColumns()),
                ));
            }

            return $this->handleRelationship(
                $relationshipMetadata,
                $relationship->getForeignKeyColumn(),
                $foreignClassProperty,
                $comparison->getValue(),
            );
        }

        if ($this->schemaMetadata->isTranslatedColumn($column)) {
            return $this->handleTranslation($comparison);
        }

        $parameterName = $column . '_' . count($this->parameters);
        $placeholder = ':' . $parameterName;
        $value = $this->walkValue($comparison->getValue());
        $type = $this->schemaMetadata->getBindingTypeForColumn($column);
        if (is_array($value)) {
            $type += Connection::ARRAY_PARAM_OFFSET;
        }

        if ($this->isInheritedColumn($column)) {
            $inheritanceMetadata = $this->schemaMetadata->getInheritanceMetadataWithColumn($column);
            assert($inheritanceMetadata !== null);
            $fullColumnName = $inheritanceMetadata->getTableName() . '.' . $column;
        } else {
            $fullColumnName = $this->tableAlias . '.' . $column;
        }
        $parameter = new Parameter($parameterName, $value, $type);

        switch ($comparison->getOperator()) {
            case Comparison::IN:
                $this->parameters[] = $parameter;

                return $this->expr()->in($fullColumnName, $placeholder);

            case Comparison::NIN:
                $this->parameters[] = $parameter;

                return $this->expr()->notIn($fullColumnName, $placeholder);

            case Comparison::EQ:
            case Comparison::IS:
                if ($this->walkValue($comparison->getValue()) === null) {
                    return $this->expr()->isNull($fullColumnName);
                }

                $this->parameters[] = $parameter;

                return $this->expr()->eq($fullColumnName, $placeholder);

            case Comparison::NEQ:
                if ($this->walkValue($comparison->getValue()) === null) {
                    return $this->expr()->isNotNull($fullColumnName);
                }

                $this->parameters[] = $parameter;

                return $this->expr()->neq($fullColumnName, $placeholder);

            case Comparison::CONTAINS:
                $parameter->setValue('%' . $this->escapeSpecialSQLValues($parameter) . '%');
                $this->parameters[] = $parameter;

                return $this->expr()->like($fullColumnName, $placeholder);

            case Comparison::STARTS_WITH:
                $parameter->setValue($this->escapeSpecialSQLValues($parameter) . '%');
                $this->parameters[] = $parameter;

                return $this->expr()->like($fullColumnName, $placeholder);

            case Comparison::ENDS_WITH:
                $parameter->setValue('%' . $this->escapeSpecialSQLValues($parameter));
                $this->parameters[] = $parameter;

                return $this->expr()->like($fullColumnName, $placeholder);

            case Comparison::GT:
            case Comparison::GTE:
            case Comparison::LT:
            case Comparison::LTE:
                $this->parameters[] = $parameter;

                return $this->expr()->comparison($fullColumnName, $comparison->getOperator(), $placeholder);

            default:
                throw new RuntimeException('Unknown comparison operator: ' . $comparison->getOperator());
        }
    }

    public function walkValue(Value $value)
    {
        return $value->getValue();
    }

    public function walkCompositeExpression(CompositeExpression $expr): DBALCompositeExpression
    {
        $expressionList = [];
        foreach ($expr->getExpressionList() as $child) {
            $expressionList[] = $this->dispatch($child);
        }

        switch ($expr->getType()) {
            case CompositeExpression::TYPE_AND:
                return $this->expr()->and(...$expressionList);

            case CompositeExpression::TYPE_OR:
                return $this->expr()->or(...$expressionList);

            default:
                throw new RuntimeException('Unknown composite ' . $expr->getType());
        }
    }

    private function escapeSpecialSQLValues(Parameter $parameter): string
    {
        $platform = $this->queryBuilder->getConnection()->getDatabasePlatform();

        return $platform->escapeStringForLike($parameter->getValue(), '\\');
    }

    private function expr(): ExpressionBuilder
    {
        return $this->queryBuilder->expr();
    }

    private function handleRelationship(
        DoctrineSchemaMetadataInterface $relationshipMetadata,
        string $foreignField,
        string $field,
        Value $value
    ): string {
        $tableName = $relationshipMetadata->getTableName();
        $parameterName = $field . '_' . count($this->parameters);
        $placeholder = ':' . $parameterName;

        $subquery = $this->queryBuilder->getConnection()->createQueryBuilder();
        $subquery->from($tableName);
        $subquery->select($tableName . '.' . $relationshipMetadata->getIdentifierColumn());
        $subquery->where(
            $subquery->expr()->in(
                $tableName . '.' . $field,
                $placeholder,
            ),
        );

        $value = $this->walkValue($value);
        $type = $relationshipMetadata->getBindingTypeForColumn($field);
        if (is_array($value)) {
            $type += Connection::ARRAY_PARAM_OFFSET;
        }
        $parameter = new Parameter($parameterName, $value, $type);
        $this->parameters[] = $parameter;

        return $this->expr()->in(
            $this->tableAlias . '.' . $foreignField,
            $subquery->getSQL(),
        );
    }

    private function handleTranslation(Comparison $comparison): string
    {
        $translationMetadata = $this->schemaMetadata->getTranslationSchemaMetadata();
        $translationForeignKeyColumn = $translationMetadata->getTranslatableIdColumn();

        $subquery = $this->queryBuilder->getConnection()->createQueryBuilder();
        $subquery->select(self::TRANSLATION_TABLE_ALIAS . '.' . $translationForeignKeyColumn);
        $subquery->from($translationMetadata->getTableName(), self::TRANSLATION_TABLE_ALIAS);
        $subquery->where($subquery->expr()->eq(
            self::TRANSLATION_TABLE_ALIAS . '.' . $translationForeignKeyColumn,
            $this->tableAlias . '.' . $this->schemaMetadata->getIdentifierColumn(),
        ));

        $innerExpressionVisitor = new self(
            $subquery,
            $this->registry,
            $translationMetadata->getTableName(),
            self::TRANSLATION_TABLE_ALIAS,
        );
        $innerCondition = $innerExpressionVisitor->walkComparison($comparison);
        $subquery->andWhere($innerCondition);
        foreach ($innerExpressionVisitor->getParameters() as $parameter) {
            $this->parameters[] = $parameter;
        }

        return $this->queryBuilder->expr()->in(
            $this->tableAlias . '.' . $this->schemaMetadata->getIdentifierColumn(),
            $subquery->getSQL(),
        );
    }

    private function isInheritedColumn(string $column): bool
    {
        return $this->schemaMetadata->getIdentifierColumn() !== $column
            && $this->schemaMetadata->isInheritedColumn($column);
    }
}
