<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Exception\SequenceAlreadyExists;
use Doctrine\DBAL\Schema\Exception\TableAlreadyExists;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderEventTest extends TestCase
{
    public function testLooksUpTablesAndSequencesInTheSchemaAndAmongTheAddedOnes(): void
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
        self::assertFalse($event->getSchema()->hasTable('added_table'));
    }

    /**
     * @param non-empty-string $name
     */
    #[DataProvider('provideExistingTableNames')]
    public function testAddingAnExistingTableFails(string $name): void
    {
        $event = $this->createEvent();
        $event->addTable(self::createTable('added_table'));

        $this->expectException(TableAlreadyExists::class);

        $event->addTable(self::createTable($name));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideExistingTableNames(): iterable
    {
        yield 'in the schema' => ['existing_table'];
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

        $this->expectException(SequenceAlreadyExists::class);

        $event->addSequence(self::createSequence($name));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function provideExistingSequenceNames(): iterable
    {
        yield 'in the schema' => ['existing_sequence'];
        yield 'added' => ['added_sequence'];
    }

    private function createEvent(): SchemaBuilderEvent
    {
        return new SchemaBuilderEvent(
            self::createStub(SchemaBuilderInterface::class),
            new Schema([self::createTable('existing_table')], [self::createSequence('existing_sequence')])
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
