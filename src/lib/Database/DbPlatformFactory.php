<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Contracts\DoctrineSchema\DbPlatformFactoryInterface as APIDbPlatformFactory;

/**
 * @internal
 */
final class DbPlatformFactory implements APIDbPlatformFactory
{
    /**
     * @var array<\Ibexa\DoctrineSchema\Database\DbPlatform\DbPlatformInterface&\Doctrine\DBAL\Platforms\AbstractPlatform>
     */
    private array $dbPlatforms = [];

    /**
     * @param iterable<\Ibexa\DoctrineSchema\Database\DbPlatform\DbPlatformInterface&\Doctrine\DBAL\Platforms\AbstractPlatform> $dbPlatforms
     */
    public function __construct(iterable $dbPlatforms)
    {
        foreach ($dbPlatforms as $dbPlatform) {
            $this->dbPlatforms[$dbPlatform->getDriverName()] = $dbPlatform;
        }
    }

    public function createDatabasePlatformFromDriverName(string $driverName): ?AbstractPlatform
    {
        return $this->dbPlatforms[$driverName] ?? null;
    }
}
