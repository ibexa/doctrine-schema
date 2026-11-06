<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\SchemaEditor;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Gives subscribers the editor of the schema being built. DBAL 4.5 deprecated changing a schema or a table in place,
 * so subscribers add and change tables and sequences with a schema editor, which the schema builder creates the
 * schema from once the event has been dispatched.
 */
class SchemaBuilderEvent extends Event
{
    private SchemaBuilderInterface $schemaBuilder;

    private SchemaEditor $schemaEditor;

    public function __construct(
        SchemaBuilderInterface $schemaBuilder,
        SchemaEditor $schemaEditor
    ) {
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaEditor = $schemaEditor;
    }

    /**
     * The editor of the schema being built. Tables and sequences added or changed with it end up in the built schema,
     * the tables with the schema's default table options under their own.
     */
    public function getSchemaEditor(): SchemaEditor
    {
        return $this->schemaEditor;
    }

    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return $this->schemaBuilder;
    }

    public function hasTable(string $name): bool
    {
        return $this->schemaEditor->create()->hasTable($name);
    }

    public function hasSequence(string $name): bool
    {
        return $this->schemaEditor->create()->hasSequence($name);
    }
}
