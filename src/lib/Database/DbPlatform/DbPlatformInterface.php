<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Database\DbPlatform;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;

/**
 * @internal
 */
interface DbPlatformInterface
{
    /**
     * Get the name of the driver associated with Database Platform implementation.
     *
     * Every Database Platform implementation should extend Doctrine AbstractPlatform
     * (or its implementation).
     *
     * @see \Doctrine\DBAL\Platforms\AbstractPlatform
     */
    public function getDriverName(): string;

    /**
     * Add event subscribers predefined and required by an implementation.
     */
    public function addEventSubscribers(EventManager $eventManager): void;

    /**
     * Add platform-based configuration to DBAL.
     */
    public function configure(Configuration $dbalConfiguration): void;
}
