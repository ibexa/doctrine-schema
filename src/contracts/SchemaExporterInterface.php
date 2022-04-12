<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

use Doctrine\DBAL\Schema\Schema;

/**
 * Export the given database Schema object to the custom Yaml format.
 */
interface SchemaExporterInterface
{
    /**
     * Export \Doctrine\DBAL\Schema object to the custom Yaml format.
     */
    public function export(Schema $schemaDefinition): string;
}

class_alias(SchemaExporterInterface::class, 'EzSystems\DoctrineSchema\API\SchemaExporter');
