<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Validator\Mapping\Loader\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

final class UnmappedItem
{
    #[Assert\Length(max: 3)]
    public string $name = '';
}
