<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures;

use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaTable;
use Symfony\Component\Validator\Constraints\EnableAutoMapping;

#[SchemaTable('unknown_table')]
#[EnableAutoMapping]
final class UnknownTableItem
{
    public string $name = '';
}
