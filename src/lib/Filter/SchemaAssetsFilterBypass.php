<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Filter;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\DoctrineSchema\SchemaAssetsFilterBypassInterface;

final class SchemaAssetsFilterBypass implements SchemaAssetsFilterBypassInterface
{
    public function call(Connection $connection, callable $callback)
    {
        $configuration = $connection->getConfiguration();
        $previousFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(static fn (): bool => true);

        try {
            return $callback();
        } finally {
            $configuration->setSchemaAssetsFilter($previousFilter ?? static fn (): bool => true);
        }
    }
}
