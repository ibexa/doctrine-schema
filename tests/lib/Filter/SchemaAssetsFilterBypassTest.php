<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Filter;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Ibexa\DoctrineSchema\Filter\SchemaAssetsFilterBypass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SchemaAssetsFilterBypassTest extends TestCase
{
    private Connection&MockObject $connection;

    private Configuration&MockObject $configuration;

    private SchemaAssetsFilterBypass $bypass;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(Configuration::class);

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getConfiguration')->willReturn($this->configuration);

        $this->bypass = new SchemaAssetsFilterBypass();
    }

    public function testTemporarilyLiftsAndRestoresTheConfiguredFilter(): void
    {
        $originalFilter = static fn (): bool => false;
        $this->configuration->expects(self::once())
            ->method('getSchemaAssetsFilter')
            ->willReturn($originalFilter);

        $appliedFilters = [];
        $this->configuration->expects(self::exactly(2))
            ->method('setSchemaAssetsFilter')
            ->willReturnCallback(static function (callable $filter) use (&$appliedFilters): void {
                $appliedFilters[] = $filter;
            });

        $result = $this->bypass->call($this->connection, static fn (): string => 'callback result');

        self::assertSame('callback result', $result);
        self::assertCount(2, $appliedFilters);
        self::assertTrue($appliedFilters[0]('any_table'));
        self::assertSame($originalFilter, $appliedFilters[1]);
    }

    public function testRestoresAPermissiveFilterWhenNoneWasConfiguredBefore(): void
    {
        $this->configuration->expects(self::once())
            ->method('getSchemaAssetsFilter')
            ->willReturn(null);

        $appliedFilters = [];
        $this->configuration->method('setSchemaAssetsFilter')
            ->willReturnCallback(static function (callable $filter) use (&$appliedFilters): void {
                $appliedFilters[] = $filter;
            });

        $this->bypass->call($this->connection, static fn (): null => null);

        self::assertTrue($appliedFilters[1]('any_table'));
    }

    public function testRestoresThePreviousFilterEvenWhenTheCallbackThrows(): void
    {
        $originalFilter = static fn (): bool => false;
        $this->configuration->expects(self::once())
            ->method('getSchemaAssetsFilter')
            ->willReturn($originalFilter);

        $appliedFilters = [];
        $this->configuration->method('setSchemaAssetsFilter')
            ->willReturnCallback(static function (callable $filter) use (&$appliedFilters): void {
                $appliedFilters[] = $filter;
            });

        $thrown = null;
        try {
            $this->bypass->call($this->connection, static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        self::assertSame('boom', $thrown->getMessage());
        self::assertSame($originalFilter, $appliedFilters[1]);
    }
}
