<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema;

class SchemaBuilderEvents
{
    public const BUILD_SCHEMA = 'ez.schema.build_schema';
}

class_alias(SchemaBuilderEvents::class, 'EzSystems\DoctrineSchema\API\Event\SchemaBuilderEvents');
