<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Database;

/**
 * Database platform identifiers recognized by {@see DatabasePlatformResolver}.
 */
enum DatabasePlatformName: string
{
    case MySQL = 'mysql';

    case PostgreSQL = 'postgresql';

    case SQLite = 'sqlite';
}
