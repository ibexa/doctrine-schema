<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

use Doctrine\DBAL\Connection;

/**
 * @internal
 */
final class TranslationDoctrineSchemaMetadata extends DoctrineSchemaMetadata implements TranslationDoctrineSchemaMetadataInterface
{
    private string $languageIdColumn;

    /**
     * Contains column name for translation relationship foreign key for translation storage metadata.
     */
    private string $translatableIdColumn;

    public function __construct(
        Connection $connection,
        ?string $className,
        string $tableName,
        array $columnToTypesMap,
        array $identifierColumns,
        string $languageIdColumn,
        string $translatableIdColumn
    ) {
        parent::__construct(
            $connection,
            $className,
            $tableName,
            $columnToTypesMap,
            $identifierColumns
        );

        $this->translatableIdColumn = $translatableIdColumn;
        $this->languageIdColumn = $languageIdColumn;
    }

    public function getTranslatableIdColumn(): string
    {
        return $this->translatableIdColumn;
    }

    public function getLanguageIdColumn(): string
    {
        return $this->languageIdColumn;
    }
}
