<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class SchemaBuilderEvent extends Event
{
    /** @var \Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface */
    private $schemaBuilder;

    /**
     * @var \Doctrine\DBAL\Schema\Schema
     */
    private $schema;

    public function __construct(SchemaBuilderInterface $schemaBuilder, Schema $schema)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->schema = $schema;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getSchemaBuilder(): SchemaBuilderInterface
    {
        return $this->schemaBuilder;
    }
}

class_alias(SchemaBuilderEvent::class, 'EzSystems\DoctrineSchema\API\Event\SchemaBuilderEvent');
