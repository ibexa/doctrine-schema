<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineSchema\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class DoctrineSchemaExtension extends Extension
{
    public const EXTENSION_NAME = 'ibexa_doctrine_schema';

    /**
     * Override default extension alias name to include Ibexa vendor in name.
     */
    public function getAlias(): string
    {
        return self::EXTENSION_NAME;
    }

    /**
     * Load Doctrine Schema Extension config.
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yaml');
        $container->addResource(new FileResource(__DIR__ . '/../Resources/config/services.yaml'));

        $loader->load('api.yaml');
        $container->addResource(new FileResource(__DIR__ . '/../Resources/config/api.yaml'));

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (isset($config['tables']['options'])) {
            $container->setParameter(
                'ibexa.schema.default_table_options',
                $config['tables']['options']
            );
        }
    }
}
