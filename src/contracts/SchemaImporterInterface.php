<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

use Doctrine\DBAL\Exception;
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
     * DBAL 4.5 deprecated changing a schema in place, so the target schema isn't changed: use the returned one, which
     * holds its tables and sequences, followed by the imported tables. The imported tables don't get the target's
     * default table options.
     *
     * @param Schema|null $targetSchema schema the returned one starts from, if not given, an empty one
     *
     * @return Schema the target's tables and sequences, and the imported tables
     *
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function importFromFile(
        string $schemaFilePath,
        ?Schema $targetSchema = null
    ): Schema;

    /**
     * Import database schema into \Doctrine\DBAL\Schema from string containing custom Yaml format.
     *
     * DBAL 4.5 deprecated changing a schema in place, so the target schema isn't changed: use the returned one, which
     * holds its tables and sequences, followed by the imported tables. The imported tables don't get the target's
     * default table options.
     *
     * @param Schema|null $targetSchema schema the returned one starts from, if not given, an empty one
     *
     * @return Schema the target's tables and sequences, and the imported tables
     *
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function importFromSource(
        string $schemaDefinition,
        ?Schema $targetSchema = null
    ): Schema;
}
