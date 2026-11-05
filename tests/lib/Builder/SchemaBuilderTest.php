<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Builder;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use Ibexa\Contracts\DoctrineSchema\SchemaBuilderEvents;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface;
use Ibexa\DoctrineSchema\Builder\SchemaBuilder;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class SchemaBuilderTest extends TestCase
{
    public function testBuildSchema(): void
    {
        $schema = $this->buildSchema(
            static function (SchemaBuilderEvent $event): void {
                $event->addTable(self::createTable('my_table'));
            }
        );

        self::assertEquals(new Schema([self::createTable('my_table')]), $schema);
    }

    public function testBuildsTheAddedTablesAndSequencesWithTheDefaultTableOptions(): void
    {
        $schema = $this->buildSchema(
            static function (SchemaBuilderEvent $event): void {
                $event->addTable(
                    self::createTable('my_table')->edit()->setOptions(['charset' => 'latin1'])->create()
                );
                $event->addSequence(Sequence::editor()->setUnquotedName('my_sequence')->create());
            },
            ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_520_ci'],
        );

        $table = $schema->getTable('my_table');
        self::assertSame('latin1', $table->getOption('charset'));
        self::assertSame('utf8mb4_unicode_520_ci', $table->getOption('collation'));
        self::assertTrue($schema->hasSequence('my_sequence'));
    }

    public function testImportsSchemaFilesIntoTheSchemaBeingBuilt(): void
    {
        $schema = $this->buildSchema(
            static function (SchemaBuilderEvent $event): void {
                $event->getSchemaBuilder()->importSchemaFromFile(
                    __DIR__ . '/../Importer/_fixtures/00-simple_pk.yaml'
                );
            },
            ['charset' => 'utf8mb4'],
            new SchemaImporter(),
        );

        self::assertSame('utf8mb4', $schema->getTable('my_table')->getOption('charset'));
    }

    /**
     * @param callable(SchemaBuilderEvent): void $onBuildSchema
     * @param array<string, mixed> $defaultTableOptions
     */
    private function buildSchema(
        callable $onBuildSchema,
        array $defaultTableOptions = [],
        ?SchemaImporterInterface $schemaImporter = null
    ): Schema {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(SchemaBuilderEvents::BUILD_SCHEMA, $onBuildSchema);

        return (new SchemaBuilder(
            $eventDispatcher,
            $schemaImporter ?? self::createStub(SchemaImporterInterface::class),
            $defaultTableOptions
        ))->buildSchema();
    }

    /**
     * @param non-empty-string $name
     */
    private static function createTable(string $name): Table
    {
        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(Column::editor()->setUnquotedName('id')->setTypeName('integer')->create())
            ->create();
    }
}
