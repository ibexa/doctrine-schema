<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Contracts\DoctrineSchema\DbPlatformFactoryInterface as APIDbPlatformFactory;

class DbPlatformFactory implements APIDbPlatformFactory
{
    /**
     * @var \Ibexa\DoctrineSchema\Database\DbPlatform\DbPlatformInterface[]
     */
    private $dbPlatforms = [];

    public function __construct(iterable $dbPlatforms)
    {
        foreach ($dbPlatforms as $dbPlatform) {
            /** @var \Ibexa\DoctrineSchema\Database\DbPlatform\DbPlatformInterface $dbPlatform */
            $this->dbPlatforms[$dbPlatform->getDriverName()] = $dbPlatform;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDatabasePlatformFromDriverName(string $driverName): ?AbstractPlatform
    {
        return $this->dbPlatforms[$driverName] ?? null;
    }
}
