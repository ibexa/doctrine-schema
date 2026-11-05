<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Collects the tables and sequences subscribers add to the schema being built. DBAL 4.5 deprecated changing a
 * schema or a table in place, so they're added to a schema editor, which the schema builder creates the schema from
 * once the event has been dispatched.
 */
class SchemaBuilderEvent extends Event
{
    private SchemaBuilderInterface $schemaBuilder;

    private SchemaEditor $schemaEditor;

    private ?Schema $schema = null;

    public function __construct(
        SchemaBuilderInterface $schemaBuilder,
        SchemaEditor $schemaEditor
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaEditor = $schemaEditor;
    }

    /**
     * The schema built so far, from the tables and sequences added with {@see addTable()} and {@see addSequence()}.
     * It's a new schema once something was added, so tables created in it aren't part of the built schema: add them
     * with {@see addTable()} instead.
     */
    public function getSchema(): Schema
    {
        return $this->schema ??= $this->schemaEditor->create();
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
        $this->schemaEditor->addTable($table);
        $this->schema = null;
    }

    /**
     * Adds a sequence built with {@see Sequence::editor()} to the schema.
     *
     * @throws SchemaException if the schema already has a sequence with that name
     */
    public function addSequence(Sequence $sequence): void
    {
        $this->schemaEditor->addSequence($sequence);
        $this->schema = null;
    }

    public function hasTable(string $name): bool
    {
        return $this->getSchema()->hasTable($name);
    }

    public function hasSequence(string $name): bool
    {
        return $this->getSchema()->hasSequence($name);
    }
}
