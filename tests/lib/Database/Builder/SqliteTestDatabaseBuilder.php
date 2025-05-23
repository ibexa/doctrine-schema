<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\Builder;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\DriverManager;
use Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform;

class SqliteTestDatabaseBuilder implements TestDatabaseBuilder
{
    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function buildDatabase(): Connection
    {
        $dbPlatform = new SqliteDbPlatform();
        $eventManager = new EventManager();
        $dbPlatform->addEventSubscribers($eventManager);
        $configuration = new Configuration();
        $configuration->setMiddlewares([new EnableForeignKeys()]);

        return DriverManager::getConnection(
            [
                'url' => 'sqlite:///:memory:',
                'platform' => $dbPlatform,
            ],
            $configuration,
            $eventManager
        );
    }
}
