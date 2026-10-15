<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;

/**
 * Import database schema from custom Yaml Doctrine Schema format into Schema object.
 *
 * @see Schema
 */
interface SchemaImporterInterface
{
    /**
     * Import database schema into \Doctrine\DBAL\Schema from file containing custom Yaml format.
     *
     * @param Schema|null $targetSchema existing schema to import into, if not given, an empty one will be created
     *
     * @return Schema imported schema
     *
     * @throws InvalidConfigurationException
     * @throws DBALException
     */
    public function importFromFile(
        string $schemaFilePath,
        ?Schema $targetSchema = null
    ): Schema;

    /**
     * Import database schema into \Doctrine\DBAL\Schema from string containing custom Yaml format.
     *
     * @param Schema|null $targetSchema existing schema to import into, if not given, an empty one will be created
     *
     * @return Schema imported schema
     *
     * @throws InvalidConfigurationException
     * @throws DBALException
     */
    public function importFromSource(
        string $schemaDefinition,
        ?Schema $targetSchema = null
    ): Schema;
}

class_alias(SchemaImporterInterface::class, 'EzSystems\DoctrineSchema\API\SchemaImporter');
