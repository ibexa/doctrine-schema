<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace EzSystems\DoctrineSchema\API\Event;

use Symfony\Component\EventDispatcher\Event;

class SchemaBuilderEvents extends Event
{
    const BUILD_SCHEMA = 'ez.schema.build_schema';
}
