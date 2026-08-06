<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformName;
use Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\DoctrineSchema\Database\DatabasePlatformResolver
 */
class DatabasePlatformResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{\Doctrine\DBAL\Platforms\AbstractPlatform, string|null}>
     */
    public function providePlatforms(): iterable
    {
        yield 'MySQL' => [new MySqlPlatform(), DatabasePlatformName::MYSQL];
        yield 'MariaDB' => [new MariaDb1027Platform(), DatabasePlatformName::MYSQL];
        yield 'PostgreSQL' => [new PostgreSqlPlatform(), DatabasePlatformName::POSTGRESQL];
        yield 'SQLite' => [new SqlitePlatform(), DatabasePlatformName::SQLITE];
    }

    /**
     * @dataProvider providePlatforms
     */
    public function testResolveName(AbstractPlatform $platform, ?string $expectedName): void
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
     * Guards against adding a name without a matching platform class candidate.
     */
    public function testEveryNameIsResolvable(): void
    {
        $resolved = [];
        foreach ($this->providePlatforms() as [$platform]) {
            $resolved[] = DatabasePlatformResolver::resolveName($platform);
        }

        self::assertEqualsCanonicalizing(
            DatabasePlatformName::all(),
            array_values(array_unique(array_filter($resolved)))
        );
    }
}
