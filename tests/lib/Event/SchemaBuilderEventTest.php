<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderEventTest extends TestCase
{
    public function testGivesTheSchemaBuiltSoFar(): void
    {
        $event = $this->createEvent();
        $schemaBefore = $event->getSchema();

        $event->addTable(self::createTable('added_table'));
        self::assertTrue($event->getSchema()->hasTable('added_table'));

        $event->addSequence(self::createSequence('added_sequence'));
        $schema = $event->getSchema();

        self::assertTrue($schema->hasTable('existing_table'));
        self::assertTrue($schema->hasTable('added_table'));
        self::assertTrue($schema->hasSequence('existing_sequence'));
        self::assertTrue($schema->hasSequence('added_sequence'));
        self::assertFalse($schemaBefore->hasTable('added_table'));
    }

    public function testLooksUpTablesAndSequencesInTheSchemaBuiltSoFar(): void
    {
        $event = $this->createEvent();
        $event->addTable(self::createTable('added_table'));
        $event->addSequence(self::createSequence('added_sequence'));

        self::assertTrue($event->hasTable('existing_table'));
        self::assertTrue($event->hasTable('added_table'));
        self::assertFalse($event->hasTable('missing_table'));
        self::assertTrue($event->hasSequence('existing_sequence'));
        self::assertTrue($event->hasSequence('added_sequence'));
        self::assertFalse($event->hasSequence('missing_sequence'));
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('provideExistingTableNames')]
    public function testAddingAnExistingTableFails(string $name): void
    {
        $event = $this->createEvent();
        $event->addTable(self::createTable('added_table'));

        $this->expectException(SchemaException::class);

        $event->addTable(self::createTable($name));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideExistingTableNames(): iterable
    {
        yield 'given with the editor' => ['existing_table'];
        yield 'added' => ['added_table'];
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('provideExistingSequenceNames')]
    public function testAddingAnExistingSequenceFails(string $name): void
    {
        $event = $this->createEvent();
        $event->addSequence(self::createSequence('added_sequence'));

        $this->expectException(SchemaException::class);

        $event->addSequence(self::createSequence($name));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideExistingSequenceNames(): iterable
    {
        yield 'given with the editor' => ['existing_sequence'];
        yield 'added' => ['added_sequence'];
    }

    private function createEvent(): SchemaBuilderEvent
    {
        return new SchemaBuilderEvent(
            self::createStub(SchemaBuilderInterface::class),
            Schema::editor()
                ->addTable(self::createTable('existing_table'))
                ->addSequence(self::createSequence('existing_sequence'))
        );
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

    /**
     * @param non-empty-string $name
     */
    private static function createSequence(string $name): Sequence
    {
        return Sequence::editor()->setUnquotedName($name)->create();
    }
}
