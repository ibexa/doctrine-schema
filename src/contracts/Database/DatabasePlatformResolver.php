<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Resolves a Doctrine database platform to one of the {@see DatabasePlatformName} cases,
 * or null if it is not one of the recognized platforms.
 *
 * Replaces the DBAL 3.10-deprecated and DBAL 4-removed AbstractPlatform::getName().
 */
final class DatabasePlatformResolver
{
    private function __construct()
    {
        // intentionally prevent instantiation
    }

    public static function resolveName(AbstractPlatform $platform): ?DatabasePlatformName
    {
        foreach (DatabasePlatformName::cases() as $name) {
            foreach (self::getPlatformClassCandidates($name) as $candidate) {
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

    /**
     * Platform class names, ordered from the most to the least specific DBAL version.
     *
     * DBAL renamed several platform classes between major versions, hence more than one candidate.
     *
     * @return array<string>
     */
    private static function getPlatformClassCandidates(DatabasePlatformName $name): array
    {
        return match ($name) {
            // base class of both the MySQL and the MariaDB platforms
            DatabasePlatformName::MySQL => ['Doctrine\\DBAL\\Platforms\\AbstractMySQLPlatform'],
            DatabasePlatformName::PostgreSQL => ['Doctrine\\DBAL\\Platforms\\PostgreSQLPlatform'],
            DatabasePlatformName::SQLite => [
                // DBAL >= 4.0
                'Doctrine\\DBAL\\Platforms\\SQLitePlatform',
                // DBAL 3.x
                'Doctrine\\DBAL\\Platforms\\SqlitePlatform',
            ],
        };
    }
}
