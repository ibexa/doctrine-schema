<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

/**
 * @internal
 *
 * Contains metadata for converting values between database & PHP.
 *
 * Conceptually based on Doctrine's ClassMetadata, but also acts as type converter.
 *
 * @see \Doctrine\Persistence\Mapping\ClassMetadata
 */
interface DoctrineSchemaMetadataInterface
{
    public function getConnection(): Connection;

    /**
     * @return class-string|null
     */
    public function getClassName(): ?string;

    /**
     * @return non-empty-string
     */
    public function getTableName(): string;

    public function getColumnType(string $column): Type;

    /**
     * @return array<string>
     */
    public function getColumns(): array;

    public function hasColumn(string $column): bool;

    public function getColumn(string $column): string;

    public function getInheritanceMetadataWithColumn(string $column): ?self;

    public function isInheritedColumn(string $column): bool;

    /**
     * Similarly to Doctrine\DBAL\Types\Type::convertToPHPValue, converts database representation to PHP
     * representation.
     *
     * @see \Doctrine\DBAL\Types\Type::convertToPHPValue
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function convertToPHPValues(array $data): array;

    /**
     * Similarly to Doctrine\DBAL\Types\Type::convertToDatabaseValue, converts PHP representation to database
     * representation.
     *
     * @see \Doctrine\DBAL\Types\Type::convertToDatabaseValue
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function convertToDatabaseValues(array $data): array;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, int>
     */
    public function getBindingTypesForData(array $data): array;

    /**
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\MappingException
     */
    public function getIdentifierColumn(): string;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getBindingTypeForColumn(string $columnName): int;

    public function setParentMetadata(self $parentMetadata): void;

    public function getParentMetadata(): ?self;

    public function getSubclassByDiscriminator(string $discriminator): self;

    /**
     * @return array<string, \Ibexa\Contracts\DoctrineSchema\Gateway\DoctrineSchemaMetadataInterface>
     */
    public function getSubclasses(): array;

    public function addSubclass(string $discriminator, self $doctrineSchemaMetadata): void;

    public function isInheritanceTypeJoined(): bool;

    public function setTranslationSchemaMetadata(TranslationDoctrineSchemaMetadataInterface $translationMetadata): void;

    public function hasTranslationSchemaMetadata(): bool;

    public function getTranslationSchemaMetadata(): TranslationDoctrineSchemaMetadataInterface;

    public function isTranslatedColumn(string $column): bool;

    public function addRelationship(DoctrineRelationshipInterface $relationship): void;

    /**
     * @return array<non-empty-string, \Ibexa\Contracts\DoctrineSchema\Gateway\DoctrineRelationshipInterface>
     */
    public function getRelationships(): array;

    public function getRelationshipByForeignKeyColumn(string $foreignProperty): DoctrineRelationshipInterface;
}
