<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\Builder;

use Doctrine\DBAL\Connection;

interface TestDatabaseBuilder
{
    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException
     */
    public function buildDatabase(): Connection;
}
