<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures;

use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaColumn;
use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaTable;
use Symfony\Component\Validator\Constraints\DisableAutoMapping;
use Symfony\Component\Validator\Constraints\EnableAutoMapping;

#[SchemaTable('test_item')]
#[EnableAutoMapping]
final class AutoMappedItem
{
    #[DisableAutoMapping]
    public string $name = '';

    public ?string $displayName = null;

    #[SchemaColumn('name')]
    public string $title = '';

    public int $position = 0;

    public string $notAColumn = '';
}
