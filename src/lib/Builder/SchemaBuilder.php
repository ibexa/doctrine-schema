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

    private ?Schema $schema = null;

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

        // A listener may build a schema while this one is being built (e.g. Doctrine's postGenerateSchema),
        // so the schema under construction is restored afterwards for the remaining listeners and imports.
        $outerSchema = $this->schema;
        $this->schema = $schema = new Schema([], [], $config);
        try {
            if ($this->eventDispatcher->hasListeners(SchemaBuilderEvents::BUILD_SCHEMA)) {
                $this->eventDispatcher->dispatch(new SchemaBuilderEvent($this, $schema), SchemaBuilderEvents::BUILD_SCHEMA);
            }
        } finally {
            $this->schema = $outerSchema ?? $schema;
        }

        return $schema;
    }

    /**
     * @throws InvalidConfigurationException
     * @throws Exception
     */
    public function importSchemaFromFile(string $schemaFilePath): Schema
    {
        return $this->schemaImporter->importFromFile($schemaFilePath, $this->schema);
    }
}
