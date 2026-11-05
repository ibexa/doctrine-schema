<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Exception\SequenceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\TableAlreadyExists;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Collects the tables and sequences subscribers add to the schema being built. DBAL 4.5 deprecated changing a
 * schema or a table in place, so the schema builder creates the schema from them once the event has been dispatched.
 */
class SchemaBuilderEvent extends Event
{
    private SchemaBuilderInterface $schemaBuilder;

    private Schema $schema;

    private SchemaEditor $addedAssets;

    /** The added tables and sequences, to look them up, as a schema editor can't. */
    private ?Schema $addedSchema = null;

    public function __construct(
        SchemaBuilderInterface $schemaBuilder,
        Schema $schema
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->schema = $schema;
        $this->addedAssets = Schema::editor();
    }

    /**
     * The schema subscribers used to change in place. It holds only the tables and sequences created in it, not the
     * ones added with {@see addTable()} and {@see addSequence()}.
     *
     * @deprecated 6.0.0 DBAL 4.5 deprecated changing a schema in place. Use {@see addTable()} and
     *             {@see addSequence()}, and {@see hasTable()} and {@see hasSequence()} to look them up.
     */
    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return $this->schemaBuilder;
    }

    /**
     * Adds a table built with {@see Table::editor()} to the schema. It gets the schema's default table options under
     * its own.
     *
     * @throws SchemaException if the schema already has a table with that name
     */
    public function addTable(Table $table): void
    {
        $name = $table->getObjectName()->toString();
        if ($this->schema->hasTable($name)) {
            throw TableAlreadyExists::new($name);
        }

        $this->addedAssets->addTable($table);
        $this->addedSchema = null;
    }

    /**
     * Adds a sequence built with {@see Sequence::editor()} to the schema.
     *
     * @throws SchemaException if the schema already has a sequence with that name
     */
    public function addSequence(Sequence $sequence): void
    {
        $name = $sequence->getObjectName()->toString();
        if ($this->schema->hasSequence($name)) {
            throw SequenceAlreadyExists::new($name);
        }

        $this->addedAssets->addSequence($sequence);
        $this->addedSchema = null;
    }

    public function hasTable(string $name): bool
    {
        return $this->schema->hasTable($name) || $this->getAddedSchema()->hasTable($name);
    }

    public function hasSequence(string $name): bool
    {
        return $this->schema->hasSequence($name) || $this->getAddedSchema()->hasSequence($name);
    }

    /**
     * @internal for the schema builder, which creates the schema from them
     *
     * @return Schema the tables and sequences added with {@see addTable()} and {@see addSequence()}
     */
    public function getAddedSchema(): Schema
    {
        return $this->addedSchema ??= $this->addedAssets->create();
    }
}
