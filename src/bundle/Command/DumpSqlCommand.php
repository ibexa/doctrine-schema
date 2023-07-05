<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineSchema\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\DoctrineSchema\Builder\SchemaBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DumpSqlCommand extends Command
{
    protected static $defaultName = 'ibexa:doctrine:schema:dump-sql';

    private Connection $db;

    private SchemaBuilder $schemaBuilder;

    public function __construct(Connection $db, SchemaBuilder $schemaBuilder)
    {
        $this->db = $db;
        $this->schemaBuilder = $schemaBuilder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        if ($file !== null) {
            $toSchema = $this->schemaBuilder->importSchemaFromFile($file);
        } else {
            $toSchema = $this->schemaBuilder->buildSchema();
        }

        $schemaManager = $this->getSchemaManager();
        $fromSchema = $this->introspectSchema($schemaManager);

        $comparator = new Comparator();
        $diff = $comparator->compare($fromSchema, $toSchema);

        $io = new SymfonyStyle($input, $output);
        foreach ($diff->toSql($this->db->getDatabasePlatform()) as $sql) {
            $io->writeln($sql . ';');
        }

        return self::SUCCESS;
    }


    private function getSchemaManager(): AbstractSchemaManager
    {
        return $this->db->getSchemaManager();
    }

    private function introspectSchema(AbstractSchemaManager $schemaManager): Schema
    {
        return $schemaManager->createSchema();
    }
}
