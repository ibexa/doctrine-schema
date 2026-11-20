<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Validator\Constraints;

use Attribute;
use Ibexa\DoctrineSchema\Validator\Constraints\SchemaMappingMarkerValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Maps a class to a schema table, whose columns are matched to properties when auto-mapping is enabled.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SchemaTable extends Constraint
{
    /**
     * @param non-empty-string $name
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly string $name,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return SchemaMappingMarkerValidator::class;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
