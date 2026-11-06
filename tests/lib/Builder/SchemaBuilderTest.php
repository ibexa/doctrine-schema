<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Builder;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use Ibexa\Contracts\DoctrineSchema\SchemaBuilderEvents;
use Ibexa\Contracts\DoctrineSchema\SchemaImporterInterface;
use Ibexa\DoctrineSchema\Builder\SchemaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SchemaBuilderTest extends TestCase
{
    public function testBuildSchema(): void
    {
        $eventDispatcher = new EventDispatcher();

        $builder = new SchemaBuilder(
            $eventDispatcher,
            self::createStub(SchemaImporterInterface::class)
        );

        $eventDispatcher->addSubscriber(
            new class() implements EventSubscriberInterface {
                public static function getSubscribedEvents(): array
                {
                    return [
                        SchemaBuilderEvents::BUILD_SCHEMA => ['onBuildSchema', 200],
                    ];
                }

                public function onBuildSchema(SchemaBuilderEvent $event): void
                {
                    $event
                        ->getSchema()->createTable('my_table');
                }
            }
        );

        $expectedSchema = new Schema();
        $expectedSchema->createTable('my_table');

        self::assertEquals(
            $expectedSchema,
            $builder->buildSchema()
        );
    }

    public function testBuildSchemaIsSafeToCallWhileSchemaIsBeingBuilt(): void
    {
        $eventDispatcher = new EventDispatcher();

        $importer = self::createStub(SchemaImporterInterface::class);
        $importer->method('importFromFile')->willReturnCallback(
            static function (
                string $path,
                Schema $schema
            ): Schema {
                $schema->createTable($path);

                return $schema;
            }
        );

        $builder = new SchemaBuilder($eventDispatcher, $importer);

        $nestedSchema = null;
        $nesting = false;
        $eventDispatcher->addListener(
            SchemaBuilderEvents::BUILD_SCHEMA,
            static function (SchemaBuilderEvent $event) use ($builder, &$nestedSchema, &$nesting): void {
                $event->getSchema()->createTable('outer_before_nested');
                if (!$nesting) {
                    $nesting = true;
                    $nestedSchema = $builder->buildSchema();
                }
            },
            250
        );
        $eventDispatcher->addListener(
            SchemaBuilderEvents::BUILD_SCHEMA,
            static function () use ($builder): void {
                $builder->importSchemaFromFile('imported_after_nested');
            },
            200
        );

        $schema = $builder->buildSchema();

        self::assertInstanceOf(Schema::class, $nestedSchema);
        self::assertNotSame($schema, $nestedSchema);
        self::assertEqualsCanonicalizing(
            ['outer_before_nested', 'imported_after_nested'],
            array_map(static fn (Table $table): string => $table->getName(), $schema->getTables())
        );
        self::assertEqualsCanonicalizing(
            ['outer_before_nested', 'imported_after_nested'],
            array_map(static fn (Table $table): string => $table->getName(), $nestedSchema->getTables())
        );
    }
}
