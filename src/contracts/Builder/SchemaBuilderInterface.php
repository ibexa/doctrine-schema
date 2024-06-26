<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Builder;

use Doctrine\DBAL\Schema\Schema;

/**
 * Doctrine\DBAL\Schema event-driven builder.
 */
interface SchemaBuilderInterface
{
    /**
     * Build schema by dispatching the SchemaBuilderEvent event.
     *
     * To build schema you should implement EventSubscriber subscribing to SchemaBuilderEvents::BUILD_SCHEMA.
     * The method handling this event accepts single argument of SchemaBuilderEvent type
     *
     * @see \Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent
     * @see \Ibexa\Contracts\DoctrineSchema\SchemaBuilderEvents::BUILD_SCHEMA
     */
    public function buildSchema(): Schema;

    /**
     * Import Schema from Yaml schema definition file into Schema object.
     */
    public function importSchemaFromFile(string $schemaFilePath): Schema;
}
