<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Builder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaApplierInterface;

final class SchemaApplier implements SchemaApplierInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function applySchema(Schema $schema, bool $dropExistingTables = false): void
    {
        $platform = $this->connection->getDatabasePlatform();

        $statements = $dropExistingTables
            ? array_merge($this->getDropStatements($schema, $platform), $schema->toSql($platform))
            : $schema->toSql($platform);

        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    /**
     * @return string[]
     */
    private function getDropStatements(Schema $newSchema, AbstractPlatform $platform): array
    {
        $existingSchema = $this->connection->getSchemaManager()->createSchema();

        $statements = [];
        // reverse table order for clean-up, so tables are dropped before the ones they reference
        foreach (array_reverse($newSchema->getTables()) as $table) {
            if ($existingSchema->hasTable($table->getName())) {
                $statements[] = $platform->getDropTableSQL($table);
            }
        }

        return $statements;
    }
}
