<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Builder;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface as APISchemaBuilder;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use Ibexa\Contracts\DoctrineSchema\Exception\InvalidConfigurationException;
use Ibexa\Contracts\DoctrineSchema\SchemaBuilderEvents;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * SchemaBuilder implementation.
 *
 * @see APISchemaBuilder
 *
 * @internal type-hint against the APISchemaBuilder
 */
class SchemaBuilder implements APISchemaBuilder
{
    private EventDispatcherInterface $eventDispatcher;

    private SchemaImporterInterface $schemaImporter;

    /** The event being dispatched, which the imported tables are added to. */
    private ?SchemaBuilderEvent $event = null;

    /** @var mixed[] */
    private array $defaultTableOptions;

    /**
     * @param mixed[] $defaultTableOptions
     */
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
     * @throws SchemaException
     */
    public function buildSchema(): Schema
    {
        $config = new SchemaConfig();
        $config->setDefaultTableOptions($this->defaultTableOptions);

        $schema = new Schema([], [], $config);
        if (!$this->eventDispatcher->hasListeners(SchemaBuilderEvents::BUILD_SCHEMA)) {
            return $schema;
        }

        $event = new SchemaBuilderEvent($this, $schema);
        $this->event = $event;
        try {
            $this->eventDispatcher->dispatch($event, SchemaBuilderEvents::BUILD_SCHEMA);
        } finally {
            $this->event = null;
        }

        $addedSchema = $event->getAddedSchema();

        return new Schema(
            [
                ...$schema->getTables(),
                ...array_map($this->withDefaultTableOptions(...), $addedSchema->getTables()),
            ],
            [...$schema->getSequences(), ...$addedSchema->getSequences()],
            $config
        );
    }

    /**
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function importSchemaFromFile(string $schemaFilePath): Schema
    {
        $schema = $this->schemaImporter->importFromFile($schemaFilePath);
        if ($this->event !== null) {
            foreach ($schema->getTables() as $table) {
                $this->event->addTable($table);
            }

            foreach ($schema->getSequences() as $sequence) {
                $this->event->addSequence($sequence);
            }
        }

        return $schema;
    }

    /**
     * Gives the table the default table options under its own, as Schema::createTable() did.
     */
    private function withDefaultTableOptions(Table $table): Table
    {
        return $table->edit()
            ->setOptions(array_merge($this->defaultTableOptions, $table->getOptions()))
            ->create();
    }
}
