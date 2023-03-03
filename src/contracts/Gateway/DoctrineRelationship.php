<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

/**
 * @internal
 */
final class DoctrineRelationship implements DoctrineRelationshipInterface
{
    /** @var class-string */
    private string $relationshipClass;

    /** @var non-empty-string */
    private string $foreignProperty;

    /** @var non-empty-string */
    private string $foreignKeyColumn;

    /** @var non-empty-string */
    private string $relatedClassIdColumn;

    /**
     * @param class-string $relationshipClass
     * @param non-empty-string $foreignProperty
     * @param non-empty-string $foreignKeyColumn
     * @param non-empty-string $relatedClassIdColumn
     */
    public function __construct(
        string $relationshipClass,
        string $foreignProperty,
        string $foreignKeyColumn,
        string $relatedClassIdColumn
    ) {
        $this->relationshipClass = $relationshipClass;
        $this->foreignProperty = $foreignProperty;
        $this->foreignKeyColumn = $foreignKeyColumn;
        $this->relatedClassIdColumn = $relatedClassIdColumn;
    }

    public function getRelationshipClass(): string
    {
        return $this->relationshipClass;
    }

    public function getRelatedClassIdColumn(): string
    {
        return $this->relatedClassIdColumn;
    }

    public function getForeignProperty(): string
    {
        return $this->foreignProperty;
    }

    public function getForeignKeyColumn(): string
    {
        return $this->foreignKeyColumn;
    }
}
