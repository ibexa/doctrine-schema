<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineSchema;

use Ibexa\Bundle\DoctrineSchema\DependencyInjection\DoctrineSchemaExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DoctrineSchemaBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new DoctrineSchemaExtension();
    }
}

class_alias(DoctrineSchemaBundle::class, 'EzSystems\DoctrineSchemaBundle\DoctrineSchemaBundle');
