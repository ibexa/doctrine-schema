<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures;

use Ibexa\Contracts\DoctrineSchema\Validator\Constraints\SchemaColumn;
use Symfony\Component\Validator\Constraints as Assert;

final class ExplicitlyMappedItem
{
    #[SchemaColumn(table: 'test_item')]
    public string $name = '';

    #[SchemaColumn(column: 'display_name', table: 'test_item')]
    public ?string $label = null;

    #[Assert\Length(min: 2)]
    #[SchemaColumn(column: 'name', table: 'test_item')]
    public string $code = '';

    #[SchemaColumn(table: 'test_item')]
    public ?string $description = null;

    #[SchemaColumn(table: 'test_item')]
    public int $priority = 0;

    #[SchemaColumn(table: 'test_item')]
    public int $position = 0;

    #[SchemaColumn(table: 'test_item')]
    public int $ownerId = 0;
}
