<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Resolves a Doctrine database platform to one of the {@see DatabasePlatformName} identifiers,
 * or null if it is not one of the recognized platforms.
 *
 * Replaces the DBAL 3.10-deprecated and DBAL 4-removed AbstractPlatform::getName().
 */
final class DatabasePlatformResolver
{
    /**
     * Platform class names, per identifier, ordered from the most to the least specific DBAL version.
     *
     * DBAL renamed several platform classes between major versions, hence more than one candidate.
     * Only the class name matters here, so candidates missing from the installed DBAL are simply
     * never matched.
     *
     * @var array<string, array<string>>
     */
    private const PLATFORM_CLASS_CANDIDATES = [
        DatabasePlatformName::MYSQL => [
            // DBAL >= 3.0, base class of both the MySQL and the MariaDB platforms
            'Doctrine\\DBAL\\Platforms\\AbstractMySQLPlatform',
            // DBAL 2.x, base class of both the MySQL and the MariaDB platforms
            'Doctrine\\DBAL\\Platforms\\MySqlPlatform',
        ],
        DatabasePlatformName::POSTGRESQL => [
            // DBAL >= 3.0
            'Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform',
            // DBAL 2.x
            'Doctrine\\DBAL\\Platforms\\PostgreSqlPlatform',
        ],
        DatabasePlatformName::SQLITE => [
            // DBAL >= 4.0
            'Doctrine\\DBAL\\Platforms\\SQLitePlatform',
            // DBAL 2.x and 3.x
            'Doctrine\\DBAL\\Platforms\\SqlitePlatform',
        ],
    ];

    private function __construct()
    {
        // intentionally prevent instantiation
    }

    /**
     * @return string|null one of the {@see DatabasePlatformName} constants, or null if unrecognized
     */
    public static function resolveName(AbstractPlatform $platform): ?string
    {
        foreach (self::PLATFORM_CLASS_CANDIDATES as $name => $candidates) {
            foreach ($candidates as $candidate) {
                // is_a() with an object subject compares against the already loaded class
                // hierarchy, so a candidate absent from the installed DBAL never matches
                // and is never autoloaded.
                if (is_a($platform, $candidate)) {
                    return $name;
                }
            }
        }

        return null;
    }
}
