<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;

class TestDatabaseFactory
{
    /** @var array<string, \Ibexa\Tests\DoctrineSchema\Database\Builder\TestDatabaseBuilder> */
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
        $name = DatabasePlatformResolver::resolveName($databasePlatform)?->value;
        if ($name === null || !isset($this->databaseBuildersForPlatforms[$name])) {
            throw new TestDatabaseConfigurationException(sprintf('Unsupported DBMS \'%s\'', $name ?? $databasePlatform::class));
        }

        return $this->databaseBuildersForPlatforms[$name]->buildDatabase();
    }
}
