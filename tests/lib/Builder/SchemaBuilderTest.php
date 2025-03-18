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
            $this->createMock(SchemaImporterInterface::class)
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

        self::assertEquals(
            new Schema([new Table('my_table')]),
            $builder->buildSchema()
        );
    }
}
