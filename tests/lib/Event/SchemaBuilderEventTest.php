<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Event;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\Event\SchemaBuilderEvent;
use PHPUnit\Framework\TestCase;

final class SchemaBuilderEventTest extends TestCase
{
    public function testLooksUpTablesAndSequencesInTheSchemaBeingBuilt(): void
    {
        $event = $this->createEvent();

        self::assertTrue($event->hasTable('existing_table'));
        self::assertFalse($event->hasTable('added_table'));
        self::assertTrue($event->hasSequence('existing_sequence'));
        self::assertFalse($event->hasSequence('added_sequence'));

        $event->getSchemaEditor()
            ->addTable(self::createTable('added_table'))
            ->addSequence(self::createSequence('added_sequence'));

        self::assertTrue($event->hasTable('added_table'));
        self::assertTrue($event->hasSequence('added_sequence'));
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
