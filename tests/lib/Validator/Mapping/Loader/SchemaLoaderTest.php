<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader;

use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use Ibexa\DoctrineSchema\Validator\Mapping\Loader\SchemaLoader;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\AutoMappedItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\ExplicitlyMappedItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\TablelessColumnItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\TableMappedItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\UnknownColumnItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\UnknownTableItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\UnmappedItem;
use Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures\YamlMappedItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SchemaLoaderTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, mixed, int}>
     */
    public static function provideMappedPropertyValues(): iterable
    {
        yield 'string column at its length' => [ExplicitlyMappedItem::class, 'name', str_repeat('a', 10), 0];
        yield 'string column over its length' => [ExplicitlyMappedItem::class, 'name', str_repeat('a', 11), 1];
        yield 'multibyte string column at its length' => [ExplicitlyMappedItem::class, 'name', str_repeat('ą', 10), 0];
        yield 'named string column over its length' => [ExplicitlyMappedItem::class, 'label', str_repeat('a', 6), 1];
        yield 'text column' => [ExplicitlyMappedItem::class, 'description', str_repeat('a', 1000), 0];
        yield 'unsigned smallint column below zero' => [ExplicitlyMappedItem::class, 'priority', -1, 1];
        yield 'unsigned smallint column over its maximum' => [ExplicitlyMappedItem::class, 'priority', 65536, 1];
        yield 'integer column at its minimum' => [ExplicitlyMappedItem::class, 'position', -2147483648, 0];
        yield 'integer column over its maximum' => [ExplicitlyMappedItem::class, 'position', 2147483648, 1];
        yield 'unsigned bigint column below zero' => [ExplicitlyMappedItem::class, 'ownerId', -1, 1];
        yield 'auto-mapped string column over its length' => [AutoMappedItem::class, 'displayName', str_repeat('a', 6), 1];
        yield 'auto-mapped integer column over its maximum' => [AutoMappedItem::class, 'position', 2147483648, 1];
        yield 'column of the class table over its length' => [AutoMappedItem::class, 'title', str_repeat('a', 11), 1];
        yield 'property with auto-mapping disabled' => [AutoMappedItem::class, 'name', str_repeat('a', 11), 0];
        yield 'property matching no column' => [AutoMappedItem::class, 'notAColumn', str_repeat('a', 11), 0];
        yield 'property with auto-mapping enabled' => [TableMappedItem::class, 'displayName', str_repeat('a', 6), 1];
        yield 'property of a class with auto-mapping not enabled' => [TableMappedItem::class, 'name', str_repeat('a', 11), 0];
        yield 'string column mapped in YAML over its length' => [YamlMappedItem::class, 'title', str_repeat('a', 11), 1];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('provideMappedPropertyValues')]
    public function testValidatesPropertiesAgainstTheirColumns(
        string $className,
        string $propertyName,
        mixed $value,
        int $expectedViolationCount
    ): void {
        $violations = $this->createValidator()->validatePropertyValue($className, $propertyName, $value);

        self::assertCount($expectedViolationCount, $violations, (string)$violations);
    }

    public function testAutoMapsTheClassesOfTheConfiguredNamespaces(): void
    {
        $validator = $this->createValidator(
            new SchemaLoader(self::createSchemaBuilder(), '{^Ibexa\\\\Tests\\\\DoctrineSchema\\\\}')
        );

        $violations = $validator->validatePropertyValue(TableMappedItem::class, 'name', str_repeat('a', 11));

        self::assertCount(1, $violations);
    }

    public function testSetsTheMaximumOfAnExistingLengthConstraint(): void
    {
        $metadata = $this->createValidator()->getMetadataFor(ExplicitlyMappedItem::class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);

        $lengthConstraints = [];
        foreach ($metadata->getPropertyMetadata('code') as $propertyMetadata) {
            foreach ($propertyMetadata->getConstraints() as $constraint) {
                if ($constraint instanceof Length) {
                    $lengthConstraints[] = $constraint;
                }
            }
        }

        self::assertCount(1, $lengthConstraints);
        self::assertSame(2, $lengthConstraints[0]->min);
        self::assertSame(10, $lengthConstraints[0]->max);
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function provideInvalidMappings(): iterable
    {
        yield 'unknown column' => [
            UnknownColumnItem::class,
            'Column "test_item.unknown" mapped to "' . UnknownColumnItem::class . '::$unknown" does not exist in the schema.',
        ];
        yield 'unknown table' => [
            UnknownTableItem::class,
            'Table "unknown_table" mapped to "' . UnknownTableItem::class . '" does not exist in the schema.',
        ];
        yield 'column without a table' => [
            TablelessColumnItem::class,
            'The schema column of "' . TablelessColumnItem::class . '::$name" has no table',
        ];
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('provideInvalidMappings')]
    public function testRejectsInvalidMappings(
        string $className,
        string $expectedMessage
    ): void {
        $validator = $this->createValidator();

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($expectedMessage);

        $validator->getMetadataFor($className);
    }

    public function testDoesNotBuildTheSchemaForUnmappedClasses(): void
    {
        $schemaBuilder = $this->createMock(SchemaBuilderInterface::class);
        $schemaBuilder->expects($this->never())->method('buildSchema');

        $violations = $this->createValidator(new SchemaLoader($schemaBuilder, '{.*}'))
            ->validatePropertyValue(UnmappedItem::class, 'name', 'abcd');

        self::assertCount(1, $violations);
    }

    public function testBuildsTheSchemaOnce(): void
    {
        $schemaBuilder = $this->createMock(SchemaBuilderInterface::class);
        $schemaBuilder->expects($this->once())->method('buildSchema')->willReturn(self::importSchema());

        $validator = $this->createValidator(new SchemaLoader($schemaBuilder));
        $validator->getMetadataFor(ExplicitlyMappedItem::class);
        $validator->getMetadataFor(AutoMappedItem::class);
    }

    private function createValidator(?SchemaLoader $loader = null): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->addYamlMapping(__DIR__ . '/_fixtures/validation.yaml')
            ->addLoader($loader ?? new SchemaLoader(self::createSchemaBuilder()))
            ->getValidator();
    }

    private static function createSchemaBuilder(): SchemaBuilderInterface
    {
        $schemaBuilder = self::createStub(SchemaBuilderInterface::class);
        $schemaBuilder->method('buildSchema')->willReturn(self::importSchema());

        return $schemaBuilder;
    }

    private static function importSchema(): Schema
    {
        return (new SchemaImporter())->importFromFile(__DIR__ . '/_fixtures/schema.yaml');
    }
}
