<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

use Doctrine\DBAL\Connection;

/**
 * Temporarily lifts whatever schema assets filter is configured on a
 * connection so a callback can see every table, then restores it.
 *
 * Doctrine\DBAL\Configuration::setSchemaAssetsFilter() is applied by
 * AbstractSchemaManager to every schema introspection call on a connection
 * (listTables(), tablesExist(), listTableDetails(), ...), not just to
 * doctrine:schema:update's comparison. Code that legitimately needs to see
 * a table hidden by such a filter - e.g. checking whether its own
 * hand-managed table already exists - has to bypass the filter for that one
 * call rather than rely on it.
 */
interface SchemaAssetsFilterBypassInterface
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function call(Connection $connection, callable $callback);
}
