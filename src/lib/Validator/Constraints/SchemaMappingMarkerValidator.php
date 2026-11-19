<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\DoctrineSchema\Validator\Constraints;

use Ibexa\DoctrineSchema\Validator\Mapping\Loader\SchemaLoader;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates nothing, as {@see SchemaLoader} turns the markers into constraints.
 *
 * @internal
 */
final class SchemaMappingMarkerValidator extends ConstraintValidator
{
    public function validate(
        mixed $value,
        Constraint $constraint
    ): void {}
}
