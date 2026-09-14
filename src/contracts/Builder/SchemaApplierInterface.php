<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Builder;

use Doctrine\DBAL\Schema\Schema;

/**
 * Executes the DDL of a built {@see \Doctrine\DBAL\Schema\Schema} against a connection.
 *
 * Complements {@see SchemaBuilderInterface}, which produces a Schema but does not apply it.
 */
interface SchemaApplierInterface
{
    /**
     * @param bool $dropExistingTables drop the schema's tables first where they already exist,
     *                                 making the call repeatable against the same connection
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function applySchema(Schema $schema, bool $dropExistingTables = false): void;
}
