<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\Builder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException;

interface TestDatabaseBuilder
{
    /**
     * @throws Exception
     * @throws TestDatabaseConfigurationException
     */
    public function buildDatabase(): Connection;
}
