<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class TestDatabaseFactory
{
    /** @var \Ibexa\Tests\DoctrineSchema\Database\Builder\TestDatabaseBuilder[] */
    private array $databaseBuildersForPlatforms = [];

    public function __construct()
    {
        $this->databaseBuildersForPlatforms = [
            'sqlite' => new Builder\SqliteTestDatabaseBuilder(),
            'mysql' => new Builder\MySqlTestDatabaseBuilder(),
        ];
    }

    /**
     * @throws \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException
     * @throws \Doctrine\DBAL\Exception
     */
    public function prepareAndConnect(AbstractPlatform $databasePlatform): Connection
    {
        $name = $databasePlatform->getName();
        if (!isset($this->databaseBuildersForPlatforms[$name])) {
            throw new TestDatabaseConfigurationException("Unsupported DBMS '{$name}'");
        }

        return $this->databaseBuildersForPlatforms[$name]->buildDatabase();
    }
}
