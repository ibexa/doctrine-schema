<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver
 */
final class DatabasePlatformResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{\Doctrine\DBAL\Platforms\AbstractPlatform, \Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName}>
     */
    public function providePlatforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform(), DatabasePlatformName::MySQL];
        yield 'MariaDB' => [new MariaDBPlatform(), DatabasePlatformName::MySQL];
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), DatabasePlatformName::PostgreSQL];
        yield 'SQLite' => [new SQLitePlatform(), DatabasePlatformName::SQLite];
    }

    /**
     * @dataProvider providePlatforms
     */
    public function testResolveName(AbstractPlatform $platform, DatabasePlatformName $expectedName): void
    {
        self::assertSame($expectedName, DatabasePlatformResolver::resolveName($platform));
    }

    public function testResolveNameReturnsNullForUnrecognizedPlatform(): void
    {
        self::assertNull(
            DatabasePlatformResolver::resolveName($this->createMock(AbstractPlatform::class))
        );
    }

    /**
     * Guards against adding a case without a matching platform class candidate.
     */
    public function testEveryCaseIsResolvable(): void
    {
        $resolved = [];
        foreach ($this->providePlatforms() as [$platform]) {
            $resolved[] = DatabasePlatformResolver::resolveName($platform);
        }

        self::assertEqualsCanonicalizing(
            DatabasePlatformName::cases(),
            array_values(array_unique(array_filter($resolved), SORT_REGULAR))
        );
    }
}
