<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

use Doctrine\DBAL\Platforms\AbstractPlatform;

interface DbPlatformFactoryInterface
{
    /**
     * Create instance of Doctrine AbstractPlatform for the given driver name.
     *
     * Factory can return null, which means that the Driver should decide.
     *
     * @see \Doctrine\DBAL\Platforms\AbstractPlatform
     * @see \Doctrine\DBAL\Driver
     *
     * @param string $driverName (e.g. 'pdo_mysql', 'pdo_pgsql', 'pdo_sqlite').
     *
     * @return \Doctrine\DBAL\Platforms\AbstractPlatform|null if null - let the Driver decide
     */
    public function createDatabasePlatformFromDriverName(string $driverName): ?AbstractPlatform;
}
