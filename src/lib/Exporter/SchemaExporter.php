<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Exporter;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\SchemaExporterInterface as APISchemaExporter;
use Ibexa\DoctrineSchema\Exporter\Table\SchemaTableExporter;
use Symfony\Component\Yaml\Yaml;

/**
 * Export the given database Schema object to the custom Yaml format.
 *
 * @internal Type-hint API interface \Ibexa\Contracts\DoctrineSchema\SchemaExporterInterface
 */
class SchemaExporter implements APISchemaExporter
{
    private SchemaTableExporter $tableExporter;

    public function __construct(SchemaTableExporter $tableYamlExporter)
    {
        $this->tableExporter = $tableYamlExporter;
    }

    /**
     * Export \Doctrine\DBAL\Schema object to the custom Yaml format.
     *
     * @return string representation of database schema in Yaml format
     */
    public function export(Schema $schemaDefinition): string
    {
        $schemaDefinitionData = [];
        foreach ($schemaDefinition->getTables() as $table) {
            $schemaDefinitionData['tables'] = array_merge(
                $schemaDefinitionData['tables'] ?? [],
                $this->tableExporter->export($table)
            );
        }

        return Yaml::dump($schemaDefinitionData, 4);
    }
}
