<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Builder;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface as APISchemaBuilder;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use Ibexa\Contracts\DoctrineSchema\SchemaBuilderEvents;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * SchemaBuilder implementation.
 *
 * @see \Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface
 *
 * @internal type-hint against the \Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface
 */
class SchemaBuilder implements APISchemaBuilder
{
    private EventDispatcherInterface $eventDispatcher;

    private SchemaImporterInterface $schemaImporter;

    private ?Schema $schema = null;

    private array $defaultTableOptions;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        SchemaImporterInterface $schemaImporter,
        array $defaultTableOptions = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->schemaImporter = $schemaImporter;
        $this->defaultTableOptions = $defaultTableOptions;
    }

    /**
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function buildSchema(): Schema
    {
        $config = new SchemaConfig();
        $config->setDefaultTableOptions($this->defaultTableOptions);

        $this->schema = new Schema([], [], $config);
        if ($this->eventDispatcher->hasListeners(SchemaBuilderEvents::BUILD_SCHEMA)) {
            $event = new SchemaBuilderEvent($this, $this->schema);
            $this->eventDispatcher->dispatch($event, SchemaBuilderEvents::BUILD_SCHEMA);
        }

        return $this->schema;
    }

    /**
     * @throws \Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException
     * @throws \Doctrine\DBAL\Exception
     */
    public function importSchemaFromFile(string $schemaFilePath): Schema
    {
        return $this->schemaImporter->importFromFile($schemaFilePath, $this->schema);
    }
}
