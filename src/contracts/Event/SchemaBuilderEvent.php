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

    /** @var list<Table> */
    private array $tables = [];

    /** @var list<Sequence> */
    private array $sequences = [];

    /** The added tables and sequences, to look them up the way a schema does. */
    private ?Schema $addedAssets = null;

    public function __construct(
        SchemaBuilderInterface $schemaBuilder,
        Schema $schema
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->schema = $schema;
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
     * @throws TableAlreadyExists
     */
    public function addTable(Table $table): void
    {
        $name = $table->getObjectName()->toString();
        if ($this->hasTable($name)) {
            throw TableAlreadyExists::new($name);
        }

        $this->tables[] = $table;
        $this->addedAssets = null;
    }

    /**
     * Adds a sequence built with {@see Sequence::editor()} to the schema.
     *
     * @throws SequenceAlreadyExists
     */
    public function addSequence(Sequence $sequence): void
    {
        $name = $sequence->getObjectName()->toString();
        if ($this->hasSequence($name)) {
            throw SequenceAlreadyExists::new($name);
        }

        $this->sequences[] = $sequence;
        $this->addedAssets = null;
    }

    public function hasTable(string $name): bool
    {
        return $this->schema->hasTable($name) || $this->getAddedAssets()->hasTable($name);
    }

    public function hasSequence(string $name): bool
    {
        return $this->schema->hasSequence($name) || $this->getAddedAssets()->hasSequence($name);
    }

    /**
     * @internal for the schema builder, which creates the schema from them
     *
     * @return list<Table>
     */
    public function getAddedTables(): array
    {
        return $this->tables;
    }

    /**
     * @internal for the schema builder, which creates the schema from them
     *
     * @return list<Sequence>
     */
    public function getAddedSequences(): array
    {
        return $this->sequences;
    }

    private function getAddedAssets(): Schema
    {
        return $this->addedAssets ??= new Schema($this->tables, $this->sequences);
    }
}
